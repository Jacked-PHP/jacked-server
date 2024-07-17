<?php

namespace JackedPhp\JackedServer\Services\Traits;

use Carbon\Carbon;
use Conveyor\Helpers\Arr;
use Exception;
use Hook\Filter;
use JackedPhp\JackedServer\Events\JackedRequestReceived;
use JackedPhp\JackedServer\Exceptions\RedirectException;
use JackedPhp\JackedServer\Helpers\Config;
use JackedPhp\JackedServer\Services\FastCgiClient;
use JackedPhp\JackedServer\Services\Response as JackedResponse;
use OpenSwoole\Http\Request;
use OpenSwoole\Coroutine\Http\Client as CoroutineHttpClient;
use OpenSwoole\Http\Response;
use Symfony\Component\Console\Style\OutputStyle;

trait HttpSupport
{
    protected function addServerInfo(array &$requestOptions, array $serverInfo = []): void
    {
        foreach ($serverInfo as $key => $value) {
            $requestOptions[$key] = $value;
        }
    }

    protected function addHeaders(array &$requestOptions, array $headers = []): void
    {
        foreach ($headers as $key => $value) {
            $key = str_replace('-', '_', $key);
            // here we are duplicating the header with the http_ prefix just in case,
            // but it seems to work without it - some review is necessary
            $requestOptions['http_' . $key] = $value;
            $requestOptions[$key] = $value;
        }
    }

    protected function addCookies(array &$requestOptions, array $cookies = []): void
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

    /**
     * @throws RedirectException
     */
    protected function gatherRequestInfo(Request $request): array
    {
        $content = $request->getContent();
        $requestOptions = $this->prepareRequestOptions(
            method: $request->getMethod(),
            serverInfo: array_change_key_case($request->server),
            header: array_change_key_case($request->header),
            cookies: array_change_key_case($request->cookie ?? []),
            contentLength: strlen($content),
        );

        $this->requestUriTweak($requestOptions);

        /** @throws RedirectException */
        $this->checkPathTrailingSlash($requestOptions);

        $this->eventDispatcher->dispatch(new JackedRequestReceived(
            requestOptions: $requestOptions,
            content: $content,
        ));

        return [ $requestOptions, $content ];
    }

    /**
     * This makes sure that there is a trailing slash for folder paths.
     *
     * @param array $requestOptions
     * @return void
     * @throws RedirectException
     */
    protected function checkPathTrailingSlash(array $requestOptions): void
    {
        $pathInfo = Arr::get($requestOptions, 'PATH_INFO');
        $documentRoot = Arr::get($requestOptions, 'DOCUMENT_ROOT');
        if (
            is_dir($documentRoot . $pathInfo)
            && substr($pathInfo, -1) !== '/'
        ) {
            throw new RedirectException($pathInfo . '/');
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param string $host
     * @param int $port
     * @param array<array-key, string> $allowedHeaders
     * @return void
     */
    protected function proxyRequest(
        Request $request,
        Response $response,
        string $host,
        int $port,
        array $allowedHeaders,
    ): void {
        $this->logger->info($this->logPrefix . 'Proxy Request: {requestInfo}', [
            'pathInfo' => $request->server['path_info'] ?? '',
            'requestOptions' => $request->server,
            'content' => $request->rawContent(),
        ]);
        $this->logger->info($this->logPrefix . 'Request Time: {time}', [
            'time' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $client = new CoroutineHttpClient($host, $port);
        $client->setHeaders([
            'Host' => $host,
            'User-Agent' => 'Jacked Server HTTP Proxy',
        ]);
        $client->set([ 'timeout' => Config::get(
            key: 'jacked-server.proxy.timeout',
            default: 5,
        )]);
        $status = $client->execute($request->server['request_uri']);

        $headers = $client->headers;
        $body = $client->body;
        $client->close();

        foreach ($headers ?? [] as $key => $value) {
            if (in_array($key, $allowedHeaders)) {
                $response->header($key, $value);
            }
        }
        $response->status($status);

        $response->end($body);
    }

    protected function executeRequest(array $requestOptions, string $content, Response $response): JackedResponse|null
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
            $client = new FastCgiClient(
                host: Config::get('fastcgi.host', '127.0.0.1'),
                port: Config::get('fastcgi.port', 9000),
            );
            $client->setConnectTimeout(Config::get('timeout', 60) * 1000);
            $client->setReadWriteTimeout(Config::get('readwrite-timeout', 60) * 1000);

            $result = $client->requestStream($requestOptions, $content, function ($data) use ($response) {
                $response->write($data);
            });
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

        if ($result === null) {
            return null;
        }

        if ($this->isValidFastcgiResponse($result)) {
            $error = $result;
        }

        return new JackedResponse($result, $error, $timeTaken);
    }

    private function requestUriTweak(array &$requestOptions): void
    {
        if (
            !empty($requestOptions['QUERY_STRING'])
            && !str_contains($requestOptions['REQUEST_URI'], '?')
        ) {
            $requestOptions['REQUEST_URI'] .= '?' . $requestOptions['QUERY_STRING'];
        }
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
            'server_protocol' => Arr::get(
                $serverInfo,
                'server_protocol',
                Config::get('server-protocol', 'HTTP/1.1'),
            ),
            'server_name' => Arr::get($requestOptions, 'http_host'),
        ])), CASE_UPPER);
    }

    private function getScriptFilename(string $requestUri): string
    {
        if (is_file($this->getDocumentRoot('') . $requestUri)) {
            return $this->getDocumentRoot($requestUri) . $requestUri;
        }

        return rtrim($this->getDocumentRoot($requestUri), '/') . '/' . basename($this->getInputFile());
    }

    private function getScriptName(string $requestUri): string
    {
        if (is_file($this->getDocumentRoot('') . '/' . ltrim($requestUri, '/'))) {
            return $requestUri;
        }

        return '';
    }

    private function getInputFile(): string
    {
        return $this->inputFile;
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
        /**
         * Description: This is a filter for the server's document root.
         * Name: jacked_document_root
         * Params:
         *   - $documentRoot: string
         *   - $requestUri: string
         * Returns: string
         */
        $documentRoot = $this->documentRoot ?? Filter::applyFilters(
            'jacked_document_root',
            Config::get(
                'openswoole-server-settings.document_root',
                __DIR__ . '/public',
            ),
            $requestUri,
        );

        if (is_dir($documentRoot . $requestUri)) {
            return $documentRoot . $requestUri;
        }

        return $documentRoot;
    }

    private function getPathInfo(array $serverInfo): string
    {
        $pathInfo = Arr::get($serverInfo, 'path_info', '');
        return $pathInfo;
    }

    private function isValidFastcgiResponse(string $fastCgiResponse): bool
    {
        return !str_contains($fastCgiResponse, 'Content-Type:')
            && !str_contains($fastCgiResponse, 'Content-type:')
            && !str_contains($fastCgiResponse, 'content-type:');
    }
}
