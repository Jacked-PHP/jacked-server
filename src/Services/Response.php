<?php

namespace JackedPhp\JackedServer\Services;

class Response
{
    private const HEADER_PATTERN = '#^([^\:]+):(.*)$#';

    private const EXCEPTIONAL_HEADERS = [
        'PHP message',
        'PHP error',
    ];

    /** @var array<string, array<int, string>> */
    private array $normalizedHeaders = [];

    /** @var array<string, array<int, string>> */
    private array $headers = [];

    private string $body = '';

    public function __construct(
        private readonly string $output,
        private readonly string $error,
        private readonly float $duration,
    ) {
        $this->parseHeadersAndBody();
    }

    private function parseHeadersAndBody(): void
    {
        $lines = explode(PHP_EOL, $this->output);
        $offset = 0;

        foreach ($lines as $i => $line) {
            $matches = [];
            if (!preg_match(self::HEADER_PATTERN, $line, $matches)) {
                break;
            }

            if ($this->handleExceptionalHeaders($matches)) {
                continue;
            }

            $offset = $i;
            $headerKey = trim($matches[1]);
            $headerValue = trim($matches[2]);

            $this->addRawHeader($headerKey, $headerValue);
            $this->addNormalizedHeader($headerKey, $headerValue);
        }

        $this->addRawHeader('X-Jacked-Server', 'Everything is worth it if the soul is not small.');

        $this->body = implode(PHP_EOL, array_slice($lines, $offset + 2));
    }

    private function handleExceptionalHeaders(array $matches): bool
    {
        if (in_array($matches[1], self::EXCEPTIONAL_HEADERS)) {
            return true;
        }
        return false;
    }

    private function addRawHeader(string $headerKey, string $headerValue): void
    {
        if (!isset($this->headers[$headerKey])) {
            $this->headers[$headerKey] = [$headerValue];

            return;
        }

        $this->headers[$headerKey][] = $headerValue;
    }

    private function addNormalizedHeader(string $headerKey, string $headerValue): void
    {
        $key = strtolower($headerKey);

        if (!isset($this->normalizedHeaders[$key])) {
            $this->normalizedHeaders[$key] = [$headerValue];

            return;
        }

        $this->normalizedHeaders[$key][] = $headerValue;
    }

    public function getHeader(string $headerKey): array
    {
        return $this->normalizedHeaders[strtolower($headerKey)] ?? [];
    }

    public function getHeaderLine(string $headerKey): string
    {
        return implode(', ', $this->getHeader($headerKey));
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * @return array{0: int, 1: string}
     */
    public function getStatus(): array
    {
        return match (current($this->getHeader('Status'))) {
            '100 Continue' => [100, 'Continue'],
            '101 Switching Protocols' => [101, 'Switching Protocols'],
            '200 OK' => [200, 'OK'],
            '201 Created' => [201, 'Created'],
            '202 Accepted' => [202, 'Accepted'],
            '203 Non-Authoritative Information' => [203, 'Non-Authoritative Information'],
            '204 No Content' => [204, 'No Content'],
            '205 Reset Content' => [205, 'Reset Content'],
            '206 Partial Content' => [206, 'Partial Content'],
            '300 Multiple Choices' => [300, 'Multiple Choices'],
            '301 Moved Permanently' => [301, 'Moved Permanently'],
            '302 Found' => [302, 'Found'],
            '303 See Other' => [303, 'See Other'],
            '304 Not Modified' => [304, 'Not Modified'],
            '305 Use Proxy' => [305, 'Use Proxy'],
            '307 Temporary Redirect' => [307, 'Temporary Redirect'],
            '400 Bad Request' => [400, 'Bad Request'],
            '401 Unauthorized' => [401, 'Unauthorized'],
            '403 Forbidden' => [403, 'Forbidden'],
            '404 Not Found' => [404, 'Not Found'],
            '405 Method Not Allowed' => [405, 'Method Not Allowed'],
            '406 Not Acceptable' => [406, 'Not Acceptable'],
            '407 Proxy Authentication Required' => [407, 'Proxy Authentication Required'],
            '408 Request Timeout' => [408, 'Request Timeout'],
            '409 Conflict' => [409, 'Conflict'],
            '410 Gone' => [410, 'Gone'],
            '411 Length Required' => [411, 'Length Required'],
            '412 Precondition Failed' => [412, 'Precondition Failed'],
            '413 Payload Too Large' => [413, 'Payload Too Large'],
            '414 URI Too Long' => [414, 'URI Too Long'],
            '415 Unsupported Media Type' => [415, 'Unsupported Media Type'],
            '416 Range Not Satisfiable' => [416, 'Range Not Satisfiable'],
            '417 Expectation Failed' => [417, 'Expectation Failed'],
            '426 Upgrade Required' => [426, 'Upgrade Required'],
            '500 Internal Server Error' => [500, 'Internal Server Error'],
            '501 Not Implemented' => [501, 'Not Implemented'],
            '502 Bad Gateway' => [502, 'Bad Gateway'],
            '503 Service Unavailable' => [503, 'Service Unavailable'],
            '504 Gateway Timeout' => [504, 'Gateway Timeout'],
            '505 HTTP Version Not Supported' => [505, 'HTTP Version Not Supported'],
            default => [200, 'OK'], // input files
        };
    }
}
