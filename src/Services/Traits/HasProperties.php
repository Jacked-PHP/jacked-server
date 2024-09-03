<?php

namespace JackedPhp\JackedServer\Services\Traits;

use JackedPhp\JackedServer\Data\ServerPersistence;
use OpenSwoole\Server as OpenSwooleBaseServer;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;

trait HasProperties
{
    public function host(string $host = '0.0.0.0'): static
    {
        $this->host = $host;

        return $this;
    }

    public function port(int $port = 8080): static
    {
        $this->port = $port;

        return $this;
    }

    public function fastcgiHost(string $fastcgiHost = '127.0.0.1'): static
    {
        $this->fastcgiHost = $fastcgiHost;

        return $this;
    }

    public function fastcgiPort(int $fastcgiPort = 9000): static
    {
        $this->fastcgiPort = $fastcgiPort;

        return $this;
    }

    public function serverProtocol(string $serverProtocol = 'HTTP/1.1'): static
    {
        $this->serverProtocol = $serverProtocol;

        return $this;
    }

    public function inputFile(string $inputFile = '/index.php'): static
    {
        $this->inputFile = $inputFile;

        return $this;
    }

    public function documentRoot(string $documentRoot = ROOT_DIR): static
    {
        $this->documentRoot = $documentRoot;

        return $this;
    }

    public function publicDocumentRoot(string $publicDocumentRoot = ROOT_DIR): static
    {
        $this->publicDocumentRoot = $publicDocumentRoot;

        return $this;
    }

    public function output(?SymfonyStyle $output = null): static
    {
        $this->output = $output;

        return $this;
    }

    public function serverPersistence(?ServerPersistence $serverPersistence = null): static
    {
        $this->serverPersistence = $serverPersistence;

        return $this;
    }

    public function eventDispatcher(?EventDispatcher $eventDispatcher = null): static
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    public function ssl(bool $ssl = false): static
    {
        $this->ssl = $ssl;

        return $this;
    }

    public function sslRedirect(bool $sslRedirect = true): static
    {
        $this->sslRedirect = $sslRedirect;

        return $this;
    }

    public function secondaryPort(int $secondaryPort = 8080): static
    {
        $this->secondaryPort = $secondaryPort;

        return $this;
    }

    public function websocketEnabled(bool $websocketEnabled = false): static
    {
        $this->websocketEnabled = $websocketEnabled;

        return $this;
    }

    public function websocketAcknowledgment(bool $websocketAcknowledgment = false): static
    {
        $this->websocketAcknowledgment = $websocketAcknowledgment;

        return $this;
    }

    public function websocketAuth(bool $websocketAuth = false): static
    {
        $this->websocketAuth = $websocketAuth;

        return $this;
    }

    public function websocketToken(?string $websocketToken = null): static
    {
        $this->websocketToken = $websocketToken;

        return $this;
    }

    public function websocketSecret(?string $websocketSecret = null): static
    {
        $this->websocketSecret = $websocketSecret;

        return $this;
    }

    /**
     * @param array<string> $requestInterceptorUris
     * @return $this
     */
    public function requestInterceptorUris(array $requestInterceptorUris = []): static
    {
        $this->requestInterceptorUris = $requestInterceptorUris;

        return $this;
    }

    public function serverType(int $serverType = OpenSwooleBaseServer::POOL_MODE): static
    {
        $this->serverType = $serverType;

        return $this;
    }

    public function auditEnabled(bool $auditEnabled = false): static
    {
        $this->auditEnabled = $auditEnabled;

        return $this;
    }

    public function timeout(int $timeout = 60): static
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function readWriteTimeout(int $readWriteTimeout = 60): static
    {
        $this->readWriteTimeout = $readWriteTimeout;

        return $this;
    }

    public function reactorNum(int $reactorNum = 4): static
    {
        $this->reactorNum = $reactorNum;

        return $this;
    }

    public function workerNum(int $workerNum = 4): static
    {
        $this->workerNum = $workerNum;

        return $this;
    }

    public function logPath(?string $logPath = null): static
    {
        $this->logPath = $logPath;

        return $this;
    }

    public function logLevel(?int $logLevel = null): static
    {
        $this->logLevel = $logLevel;

        return $this;
    }

    public function sslCertFile(?string $sslCertFile = null): static
    {
        $this->sslCertFile = $sslCertFile;

        return $this;
    }

    public function sslKeyFile(?string $sslKeyFile = null): static
    {
        $this->sslKeyFile = $sslKeyFile;

        return $this;
    }
}
