<?php

namespace JackedPhp\JackedServer\Services;

use Adoy\FastCGI\Client;
use Exception;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use JackedPhp\JackedServer\Events\JackedRequestError;
use JackedPhp\JackedServer\Events\JackedRequestFinished;
use JackedPhp\JackedServer\Events\JackedRequestReceived;
use JackedPhp\JackedServer\Events\JackedServerStarted;
use JackedPhp\JackedServer\Services\Response as JackedResponse;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Server as OpenSwooleBaseServer;
use OpenSwoole\Http\Server as OpenSwooleServer;
use Psr\Log\LoggerInterface;
use OpenSwoole\Util;

class Server
{
    private LoggerInterface $logger;
    private string $logPrefix = 'JackedServer: ';
    private string $inputFile;

    public function __construct(
        private readonly ?string $host = null,
        private readonly ?int $port = null,
        ?string $inputFile = null,
        private readonly ?string $documentRoot = null,
        private readonly ?string $publicDocumentRoot = null,
        private readonly ?OutputStyle $output = null,
    ) {
        $this->inputFile = $inputFile ?? config(
            'jacked-server.input-file',
            public_path('index.php'),
        );
        $this->logger = Log::build([
            'driver' => config('jacked-server.log.driver-name', 'single'),
            'path' => config('jacked-server.log.path', storage_path('logs/jacked-server.log')),
            'replace_placeholders' => config('jacked-server.log.replace-placeholders', 'single'),
        ]);
    }

    public function run(): void
    {
        $server = new OpenSwooleServer(
            $this->host ?? config('jacked-server.host', '0.0.0.0'),
            $this->port ?? config('jacked-server.port', 8080),
            config('jacked-server.server-type', OpenSwooleBaseServer::POOL_MODE),
        );
        $server->set(config('jacked-server.openswoole-server-settings', [
            'document_root' => $this->publicDocumentRoot ?? public_path(),
            'enable_static_handler' => true,
            'static_handler_locations' => [ '/imgs', '/css' ],
            // reactor and workers
            'reactor_num' => Util::getCPUNum() + 2,
            'worker_num' => Util::getCPUNum() + 2,
        ]));
        $server->on('start', [$this, 'handleStart']);

        // ssl
        if (config('jacked-server.ssl-enabled', false)) {
            $sslPort = $server->listen(
                $this->host ?? config('jacked-server.host', '0.0.0.0'),
                config('jacked-server.ssl-port', 443),
                SWOOLE_SOCK_TCP | SWOOLE_SSL
            );
            $sslPort->set([
                'ssl_cert_file' => base_path('packages/jacked-php/jacked-server/js-cert.pem'),
                'ssl_key_file' => base_path('packages/jacked-php/jacked-server/js-key.pem'),
                'open_http_protocol' => true,
            ]);
            $sslPort->on('request', [$this, 'handleRequest']);
            $server->on('request', [$this, 'sslRedirectRequest']);
        } else {
            $server->on('request', [$this, 'handleRequest']);
        }

        $server->start();
    }

    public function sslRedirectRequest(Request $request, Response $response) {
        $response->status(301);
        $response->header(
            'Location',
            'https://' . $request->header['host'] . $request->server['request_uri'],
        );
        $response->end();
    }

    public function handleStart(OpenSwooleServer $server): void
    {
        $message = 'OpenSwoole Server started'
            . ' on ' . $server->host . ':' . $server->port
            . ' at ' . Carbon::now()->format('Y-m-d H:i:s');
        $this->logger->info($this->logPrefix . $message);

        if ($this->output instanceof OutputStyle) {
            $this->output->success($message);
        } else {
            echo $message . PHP_EOL;
        }

        event(JackedServerStarted::class, $server->host, $server->port);
    }

    public function handleRequest(Request $request, Response $response): void
    {
        [ $requestOptions, $content ] = $this->gatherRequestInfo($request);

        $jackedResponse = $this->executeRequest($requestOptions, $content);

        $this->sendResponse($response, $jackedResponse);
    }

    private function sendResponse(Response $response, JackedResponse $jackedResponse): void
    {
        if (!empty($jackedResponse->getError())) {
            event(
                JackedRequestError::class,
                500,
                $jackedResponse->getHeaders(),
                $jackedResponse->getBody()
            );
            $response->status(500);
            $response->write($jackedResponse->getError());
            return;
        }

        // prepare response info
        $headers = $jackedResponse->getHeaders();
        $status = $jackedResponse->getStatus();
        $body = $jackedResponse->getBody();
        if ($status[0] >= 400 &&  $status[0] < 600) {
            event(JackedRequestError::class, $status[0], $headers, $jackedResponse->getError());
        } else {
            event(JackedRequestFinished::class, $status[0], $headers, $body);
        }

        // header
        foreach ($headers as $headerKey => $headerValue) {
            $response->header($headerKey, $headerValue);
        }

        // status
        $response->status(...$status);

        // content
        if (in_array($status[0], [301, 302, 303, 307])) {
            $response->redirect($headers['Location'][0]);
        } elseif (!empty($body)) {
            $response->write($body);
        } else {
            $response->end();
        }
    }

    private function isValidFastcgiResponse(string $fastCgiResponse): bool
    {
        return !str_contains($fastCgiResponse, 'Content-Type:')
            && !str_contains($fastCgiResponse, 'Content-type:')
            && !str_contains($fastCgiResponse, 'content-type:');
    }

    private function gatherRequestInfo(Request $request): array
    {
        $content = $request->getContent();
        $requestOptions = $this->prepareRequestOptions(
            method: $request->getMethod(),
            serverInfo: array_change_key_case($request->server),
            header: array_change_key_case($request->header),
            cookies: array_change_key_case($request->cookie ?? []),
            contentLength: strlen($content),
        );
        event(JackedRequestReceived::class, $requestOptions, $content);

        return [ $requestOptions, $content ];
    }

    private function prepareRequestOptions(
        string $method,
        array $serverInfo,
        array $header,
        array $cookies,
        int $contentLength,
    ): array {
        $this->logger->debug($this->logPrefix . ' Debug: prepare request options', [
            'method' => $method,
            'serverInfo' => $serverInfo,
            'header' => $header,
            'cookies' => $cookies,
            'contentLength' => $contentLength,
        ]);

        $requestOptions = [];
        $this->addServerInfo($requestOptions, $serverInfo);
        $this->addHeaders($requestOptions, $header);
        $this->addCookies($requestOptions, $cookies);

        $requestUri = Arr::get($serverInfo, 'request_uri', '');
        return array_change_key_case(array_filter(array_merge($requestOptions, [
            'path_info' => $this->getPathInfo($serverInfo),
            'document_root' => $this->getDocumentRoot(''),
            'request_method' => $method,
            'script_name' => $this->getScriptName($requestUri),
            'script_filename' => $this->getScriptFilename($requestUri),
            'content_length' => $contentLength,
            'server_protocol' => Arr::get($serverInfo, 'server_protocol', config('jacked-server.server-protocol', 'HTTP/1.1')),
            'server_name' => Arr::get($requestOptions, 'http_host'),
        ])), CASE_UPPER);
    }

    private function getPathInfo(array $serverInfo): string
    {
        $pathInfo = Arr::get($serverInfo, 'path_info', '');
        return rtrim(dirname($pathInfo), '/');
    }

    /**
     * If the server root is needed, pass the $requestUri as empty string('').
     * If the file requested root is needed, pass the real $requestUri.
     *
     * @param string $requestUri
     * @return string
     */
    private function getDocumentRoot(string $requestUri): string
    {
        $documentRoot = $this->documentRoot
            ?? config('jacked-server.openswoole-server-settings.document_root')
            ?? public_path();

        if (is_dir($documentRoot . $requestUri)) {
            return $documentRoot . $requestUri;
        }

        return $documentRoot;
    }

    private function getInputFile(): string
    {
        return $this->inputFile;
    }

    private function getScriptName(string $requestUri): string
    {
        if (is_file($this->getDocumentRoot('') . '/' . ltrim($requestUri, '/'))) {
            return $requestUri;
        }

        return (!empty($requestUri) ? rtrim($requestUri, '/') . '/' : '')
            . basename($this->getInputFile());
    }

    private function getScriptFilename(string $requestUri): string
    {
        if (is_file($this->getDocumentRoot('') . $requestUri)) {
            return $this->getDocumentRoot($requestUri) . $requestUri;
        }

        return rtrim($this->getDocumentRoot($requestUri), '/') . '/' . basename($this->getInputFile());
    }

    private function addServerInfo(array &$requestOptions, array $serverInfo = []): void
    {
        foreach ($serverInfo as $key => $value) {
            $requestOptions[$key] = $value;
        }
    }

    private function addHeaders(array &$requestOptions, array $headers = []): void
    {
        foreach ($headers as $key => $value) {
            $key = str_replace('-', '_', $key);
            $requestOptions['http_' . $key] = $value;
            $requestOptions[$key] = $value;
        }
    }

    private function addCookies(array &$requestOptions, array $cookies = []): void
    {
        $cookieStr = '';
        foreach ($cookies ?? [] as $key => $value) {
            $cookieStr .= $key . '=' . $value . '; ';
        }

        $cookieStr = rtrim($cookieStr, '; ');
        if (!empty($cookieStr)) {
            $requestOptions['http_cookie'] = $cookieStr;
        }
    }

    private function executeRequest(array $requestOptions, string $content): JackedResponse
    {
        $result = '';
        $error = '';

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        try {
            $this->logger->info($this->logPrefix . 'Request: {requestInfo}', [
                'pathInfo' => Arr::get($requestOptions, 'PATH_INFO'),
                'requestOptions' => $requestOptions,
                'content' => $content,
            ]);
            $this->logger->info($this->logPrefix . 'Request Time: {time}', [
                'time' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
            $result = (new Client(
                host: config('jacked-server.fastcgi.host', '127.0.0.1'),
                port: config('jacked-server.fastcgi.port', 9000),
            ))->request($requestOptions, $content);
        } catch (Exception $e) {
            $error = $e->getMessage();

            if ($this->output instanceof OutputStyle) {
                $this->output->error('Jacked Server Error: ' . $error);
            } else {
                echo 'Jacked Server Error: ' . $error . PHP_EOL;
            }

            $this->logger->info($this->logPrefix . 'Request Error: {errorMessage}', [
                'errorMessage' => $error,
            ]);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        $timeTaken = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        $this->logger->info($this->logPrefix . 'Request Time taken: {timeTaken}', [
            'timeTaken' => round($timeTaken, 5) . ' seconds',
        ]);
        $this->logger->info($this->logPrefix . 'Request Memory used: {memoryUsed}', [
            'memoryUsed' => number_format($memoryUsed) . ' bytes',
        ]);

        if ($this->isValidFastcgiResponse($result)) {
            $error = $result;
        }

        return new JackedResponse($result, $error, $timeTaken);
    }
}
