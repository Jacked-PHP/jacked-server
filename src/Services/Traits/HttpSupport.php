<?php

namespace JackedPhp\JackedServer\Services\Traits;

use Carbon\Carbon;
use Conveyor\Helpers\Arr;
use Exception;
use Hook\Filter;
use JackedPhp\JackedServer\Constants;
use JackedPhp\JackedServer\Events\JackedRequestReceived;
use JackedPhp\JackedServer\Exceptions\RedirectException;
use JackedPhp\JackedServer\Helpers\Debug;
use JackedPhp\JackedServer\Services\FastCgiClient;
use JackedPhp\JackedServer\Services\Response as JackedResponse;
use Monolog\Level;
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
            // add extra headers with http_ prefix
            $key = str_replace('-', '_', $key);
            // here we are duplicating the header with the http_ prefix just in case,
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

    protected function executeRequest(array $requestOptions, string $content, Response $response): JackedResponse|null
    {
        $result = '';
        $error = '';

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        try {
            $this->report($this->logPrefix . 'Request: {requestMethod} {pathInfo}', context: [
                'requestMethod' => Arr::get($requestOptions, 'REQUEST_METHOD'),
                'pathInfo' => Arr::get($requestOptions, 'PATH_INFO'),
                'requestOptions' => $requestOptions,
                'content' => $content,
            ], skipPrint: true);

            if (
                IS_PHAR
                && !file_exists(Arr::get($requestOptions, 'SCRIPT_FILENAME'))
                && Arr::get($requestOptions, 'REQUEST_URI') === '/'
            ) {
                $this->report($this->logPrefix . 'No handler beyond Jacked Server.', context: [
                    'time' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);
                $response->write('<div style="width: 100%; text-align: center;">-- Jacked Server --</div>');
                goto end_of_try;
            }

            $client = new FastCgiClient(
                host: $this->fastcgiHost,
                port: $this->fastcgiPort,
            );
            $client->setConnectTimeout($this->timeout * 1000);
            $client->setReadWriteTimeout($this->readWriteTimeout * 1000);

            $result = $client->requestStream($requestOptions, $content, function ($data) use ($response) {
                $response->write($data);
            });

            end_of_try:
        } catch (Exception $e) {
            $this->report($this->logPrefix . 'Jacked Server Error: ' . $e->getMessage(),
                level: Level::Error);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        $timeTaken = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        $this->report($this->logPrefix . 'Request Time taken: {timeTaken}', context: [
            'timeTaken' => round($timeTaken, 5) . ' seconds',
        ], level: Level::Notice);
        $this->report($this->logPrefix . 'Request Memory used: {memoryUsed}', context: [
            'memoryUsed' => number_format($memoryUsed) . ' bytes',
        ], level: Level::Notice);

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
        if ($this->debug) {
            $options = [
                'method' => $method,
                'serverInfo' => $serverInfo,
                'header' => $header,
                'cookies' => $cookies,
                'contentLength' => $contentLength,
            ];
            $this->report(
                message: "Prepare request options\n\n" . Debug::dumpIo($options),
                context: $options,
                level: Level::Debug,
            );
        }

        $requestOptions = [];
        $this->addServerInfo($requestOptions, $serverInfo);
        $this->addHeaders($requestOptions, $header);
        $this->addCookies($requestOptions, $cookies);

        $requestUri = Arr::get($serverInfo, 'request_uri', '');

        return array_change_key_case(array_merge(
            $requestOptions,
            $this->getProjectLocation(header: $header, serverInfo: $serverInfo, requestUri: $requestUri),
            [
                'request_method' => $method,
                'content_length' => $contentLength,
                'server_protocol' => Arr::get(
                    $serverInfo,
                    'server_protocol',
                    $this->serverProtocol,
                ),
                'server_name' => Arr::get($requestOptions, 'http_host'),
            ],
        ), CASE_UPPER);
    }

    private function getProjectLocation(array $header, array $serverInfo, string $requestUri): array
    {
        /**
         * Description: This is a filter for the server's document root.
         * Name: Constants::ROUTING_FILTER
         * Params:
         *   - $param1: array{path_info: string, document_root: string, script_name: string, script_filename: string}
         *   - $param2: array<mixed>
         * Returns: string
         */
        $route = Filter::applyFilters(
            Constants::ROUTING_FILTER,
            [
                'path_info' => $this->getPathInfo($serverInfo),
                'document_root' => $this->getDocumentRoot(''),
                'script_name' => $this->getScriptName($requestUri),
                'script_filename' => $this->getScriptFilename($requestUri),
            ],
            $header,
        );

        if ($this->debug) {
            $this->report(
                message: "Routing info: \n\n" . Debug::dumpIo($route),
                context: $route,
                level: Level::Debug,
            );
        }

        return $route;
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
            $this->documentRoot,
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
