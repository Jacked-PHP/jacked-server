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
use OpenSwoole\Constant;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Server as OpenSwooleBaseServer;
use OpenSwoole\WebSocket\Server as OpenSwooleServer;
use OpenSwoole\WebSocket\Frame;
use Psr\Log\LoggerInterface;
use OpenSwoole\Util;
use Conveyor\SocketHandlers\SocketMessageRouter as Conveyor;

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
        $ssl = config('jacked-server.ssl-enabled', false);
        $primaryPort = $ssl ? config('jacked-server.ssl-port', 443) : config('jacked-server.port', 8080);
        $serverConfig = array_merge(config('jacked-server.openswoole-server-settings', [
            'document_root' => $this->publicDocumentRoot ?? public_path(),
            'enable_static_handler' => true,
            'static_handler_locations' => [ '/imgs', '/css' ],
            // reactor and workers
            'reactor_num' => Util::getCPUNum() + 2,
            'worker_num' => Util::getCPUNum() + 2,
        ]), ($ssl ? [
            'ssl_cert_file' => config('jacked-server.ssl-cert-file'),
            'ssl_key_file' => config('jacked-server.ssl-key-file'),
            'open_http_protocol' => true,
        ] : []));

        $server = new OpenSwooleServer(
            $this->host ?? config('jacked-server.host', '0.0.0.0'),
            $this->port ?? $primaryPort,
            config('jacked-server.server-type', OpenSwooleBaseServer::POOL_MODE),
            $ssl ? Constant::SOCK_TCP | Constant::SSL : Constant::SOCK_TCP,
        );
        $server->set($serverConfig);
        $server->on('start', [$this, 'handleStart']);

        // ssl
        if ($ssl) {
            $secondaryPort = $server->listen(
                $this->host ?? config('jacked-server.host', '0.0.0.0'),
                config('jacked-server.port', 8080),
                Constant::SOCK_TCP
            );

            $secondaryPort->on('request', [$this, 'sslRedirectRequest']);
        }

        $server->on('request', [$this, 'handleRequest']);
        $server->on('message', [$this, 'handleWsMessage']);
        $server->on('handshake', [$this, 'handleWsHandshake']);
        $server->on('open', [$this, 'handleWsOpen']);

        $server->start();
    }

    public function handleWsOpen(OpenSwooleServer $server, Request $request): void
    {
        $message = 'OpenSwoole Connection opened'
            . ' with FD: ' . $request->fd
            . ' on ' . $server->host . ':' . $server->port
            . ' at ' . Carbon::now()->format('Y-m-d H:i:s');
        $this->logger->info($this->logPrefix . $message);
    }

    public function handleWsHandshake(Request $request, Response $response): bool
    {
        // evaluate intention to upgrade to websocket
        try {
            $headers = $this->processSecWebSocketKey($request);
        } catch (Exception $e) {
            $response->status(400);
            $response->end($e->getMessage());
            return false;
        }

        // check for authorization
        try {
            $authToken = $request->header['authorization'] ?? null;
            if ($authToken !== 'YOUR_SECRET_TOKEN') {
                throw new Exception('Unauthorized');
            }
        } catch (Exception $e) {
            $response->status(401);
            $response->end($e->getMessage());
            return false;
        }

        foreach($headers as $headerKey => $val) {
            $response->header($headerKey, $val);
        }

        $response->status(101);
        $response->end();

        return true;
    }

    public function handleWsMessage(OpenSwooleServer $server, Frame $frame): void
    {
        $this->logger->info($this->logPrefix . ' Message received from ' . $frame->fd);
        Conveyor::run($frame->data, $frame->fd, $server);
    }

    public function sslRedirectRequest(Request $request, Response $response): void
    {
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

    /**
     * @param Request $request
     * @return array
     * @throws Exception
     */
    private function processSecWebSocketKey(Request $request): array
    {
        $secWebSocketKey = $request->header['sec-websocket-key'];
        $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';

        if (
            0 === preg_match($patten, $secWebSocketKey)
            || 16 !== strlen(base64_decode($secWebSocketKey))
        ) {
            throw new Exception('Invalid Sec-WebSocket-Key');
        }

        $key = base64_encode(sha1($request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => $key,
            'Sec-WebSocket-Version' => '13',
        ];

        if(isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        return $headers;
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
        return Arr::get($serverInfo, 'path_info', '');
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
