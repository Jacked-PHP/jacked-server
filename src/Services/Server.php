<?php

namespace JackedPhp\JackedServer\Services;

use Carbon\Carbon;
use Conveyor\Constants;
use Conveyor\ConveyorServer;
use Conveyor\Events\MessageReceivedEvent;
use Conveyor\Events\PreServerStartEvent;
use Conveyor\Events\ServerStartedEvent;
use Conveyor\Helpers\Arr;
use Conveyor\SubProtocols\Conveyor\Conveyor;
use Conveyor\SubProtocols\Conveyor\Persistence\Interfaces\GenericPersistenceInterface;
use Exception;
use Hook\Action;
use Hook\Filter;
use Illuminate\Support\Str;
use JackedPhp\JackedServer\Data\OpenSwooleConfig;
use JackedPhp\JackedServer\Data\ServerPersistence;
use JackedPhp\JackedServer\Events\JackedRequestError;
use JackedPhp\JackedServer\Events\JackedRequestFinished;
use JackedPhp\JackedServer\Events\JackedServerRequestIntercepted;
use JackedPhp\JackedServer\Events\JackedServerStarted;
use JackedPhp\JackedServer\Exceptions\AuthorizationException;
use JackedPhp\JackedServer\Exceptions\RedirectException;
use JackedPhp\JackedServer\Helpers\Config;
use JackedPhp\JackedServer\Services\Logger\EchoHandler;
use JackedPhp\JackedServer\Services\Response as JackedResponse;
use JackedPhp\JackedServer\Services\Traits\HasMonitor;
use JackedPhp\JackedServer\Services\Traits\HasProperties;
use JackedPhp\JackedServer\Services\Traits\HttpSupport;
use JackedPhp\JackedServer\Services\Traits\WebSocketSupport;
use JackedPhp\JackedServer\Services\Traits\HasAuthorizationTokenSupport;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use OpenSwoole\Constant;
use OpenSwoole\Coroutine\Http\Client as CoroutineHttpClient;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Server as OpenSwooleBaseServer;
use OpenSwoole\WebSocket\Server as OpenSwooleServer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Throwable;
use JackedPhp\JackedServer\Constants as JackedServerConstants;

class Server
{
    use HttpSupport;
    use WebSocketSupport;
    use HasAuthorizationTokenSupport;
    use HasMonitor;
    use HasProperties;

    private LoggerInterface $logger;

    private string $logPrefix = 'JackedServer: ';

    private string $host = '0.0.0.0';

    private int $port = 8080;

    /**
     * @var string FastCGI host. Default is "unix:///run/php/php-fpm.sock",
     *             alternative is "127.0.0.1".
     */
    private string $fastcgiHost = 'unix:///run/php/php-fpm.sock';

    /**
     * @var int FastCGI port. Default is -1, alternative is 9000.
     */
    private int $fastcgiPort = -1;

    private string $serverProtocol = 'HTTP/1.1';

    private string $inputFile = '/index.php';

    private ?string $documentRoot = ROOT_DIR;

    private ?string $publicDocumentRoot = ROOT_DIR;

    private ?SymfonyStyle $output = null;

    private ?ServerPersistence $serverPersistence = null;

    private ?EventDispatcher $eventDispatcher = null;

    private bool $ssl = false;

    private bool $sslRedirect = true;

    private int $secondaryPort = 8080; // used for ssl cases

    private bool $websocketEnabled = false;

    private bool $websocketAcknowledgment = false;

    private bool $websocketAuth = false;

    private ?string $websocketToken = null;

    private ?string $websocketSecret = null;

    private array $requestInterceptorUris = [];

    private int $serverType = OpenSwooleBaseServer::POOL_MODE;

    private bool $auditEnabled = false;

    private int $timeout = 60;

    private int $readWriteTimeout = 60;

    private int $reactorNum = 4;

    private int $workerNum = 4;

    private ?string $logPath = null;

    private ?int $logLevel = null;

    private ?string $sslCertFile = null;

    private ?string $sslKeyFile = null;

    public static function init(): static
    {
        return new static();
    }

    /**
     * @throws Exception
     */
    public function run(): ConveyorServer
    {
        $this->eventDispatcher = $this->eventDispatcher ?? new EventDispatcher();

        $this->prepareLogger();

        $this->executePreServerActions();

        return ConveyorServer::start(
            host: $this->host,
            port: $this->port,
            mode: $this->serverType,
            ssl: $this->ssl ? Constant::SOCK_TCP | Constant::SSL : Constant::SOCK_TCP,
            serverOptions: $this->getServerConfig()->getSnakeCasedData(),
            conveyorOptions: [
                Constants::USE_ACKNOWLEDGMENT => $this->websocketAcknowledgment,
            ],
            eventListeners: [
                Constants::EVENT_SERVER_STARTED => fn(ServerStartedEvent $event)
                    => $this->handleStart($event->server),
                Constants::EVENT_PRE_SERVER_START => function (PreServerStartEvent $event) {
                    if ($this->ssl && $this->sslRedirect) {
                        $event->server->listen(
                            host: $event->server->host,
                            port: $this->secondaryPort,
                            sockType: Constant::SOCK_TCP,
                        )->on('request', [$this, 'sslRedirectRequest']);
                    }
                    $event->server->on('handshake', [$this, 'handleWsHandshake']);
                },
                Constants::EVENT_MESSAGE_RECEIVED => fn(MessageReceivedEvent $event)
                    => $this->handleWsMessage($event),
            ],
            persistence: $this->getConveyorPersistence(),
        );
    }

    /**
     * @return array<GenericPersistenceInterface>
     */
    private function getConveyorPersistence(): array
    {
        return array_merge(
            Conveyor::defaultPersistence(),
            $this->serverPersistence->conveyorPersistence,
        );
    }

    /**
     * Here is where we activate features.
     */
    private function executePreServerActions(): void
    {
        if ($this->websocketEnabled && $this->websocketAuth) {
            $this->activateWsAuth();
        }

        Filter::addFilter(
            tag: Constants::FILTER_REQUEST_HANDLER,
            functionToAdd: fn() => [$this, 'handleRequest'],
        );

        Action::doAction(JackedServerConstants::PRE_SERVER_ACTION, $this);
    }

    public function handleWsHandshake(Request $request, Response $response): bool
    {
        if (!$this->websocketEnabled) {
            $this->logger->info($this->logPrefix . ' ws 2');
            $response->status(401);
            $response->end('WebSocket Not enabled!');
            return false;
        }

        $this->logger->info($this->logPrefix . ' Handshake received from ' . $request->fd);

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
            if ($this->websocketAuth) {
                parse_str(Arr::get($request->server, 'query_string') ?? '', $query);
                $token = Arr::get($query, 'token');

                if (null === $token || !$this->hasToken($token)) {
                    throw new AuthorizationException('WebSocket Connection Not Authorized!');
                }
            }
        } catch (AuthorizationException $e) {
            $this->logger->error($this->logPrefix . $e->getMessage(), [
                'file' => __FILE__ . ':' . __LINE__,
            ]);
            $response->status(401);
            $response->end($e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->logger->error($this->logPrefix . 'Websocket Handshake Exception: ' . $e->getMessage(), [
                'file' => __FILE__ . ':' . __LINE__,
            ]);
            $response->status(500);
            $response->end($e->getMessage());
            return false;
        }

        foreach ($headers as $headerKey => $val) {
            $response->header($headerKey, $val);
        }

        $response->status(101);
        $response->end();

        return true;
    }

    public function handleWsMessage(MessageReceivedEvent $event): void
    {
        $parsedData = json_decode($event->data);

        if (null === $parsedData) {
            return;
        }

        $this->logger->info($this->logPrefix . ' Message received from ' . $parsedData->fd, [
            'event-data' => $event->data,
        ]);
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

        $this->print($message, 'success');

        $this->eventDispatcher->dispatch(new JackedServerStarted(
            host: $server->host,
            port: $server->port,
        ));
    }

    public function handleRequest(Request $request, Response $response): void
    {
        $this->notifyRequestToMonitor($request);

        /**
         * Description: This is a filter for requests interceptor.
         * Name: JackedServerConstants::INTERCEPT_REQUEST
         * Params:
         *   - $value: array<string> Interceptors.
         * Returns: array<string>
         */
        $interceptedUris = Filter::applyFilters(
            tag: JackedServerConstants::INTERCEPT_REQUEST,
            value: $this->requestInterceptorUris,
        );

        if (in_array($request->server['request_uri'], $interceptedUris)) {
            $this->eventDispatcher->dispatch(new JackedServerRequestIntercepted(
                request: $request,
                response: $response,
            ));
            return;
        }

        try {
            [ $requestOptions, $content ] = $this->gatherRequestInfo($request);
        } catch (RedirectException $e) {
            $response->status(302);
            $response->redirect($e->getMessage());
            return;
        }

        /** @var ?JackedResponse $jackedResponse */
        $jackedResponse = $this->executeRequest($requestOptions, $content, $response);

        if (null !== $jackedResponse) {
            $this->sendResponse($response, $jackedResponse);
        }
    }

    protected function sendWsMessage(string $channel, string $message): void
    {
        $client = new CoroutineHttpClient($this->host, $this->port);
        $client->setHeaders([
            'Host' => $this->host . ':' . $this->port,
            'User-Agent' => 'Jacked Server HTTP Proxy',
            'Content-Type' => 'application/json',
        ]);
        $client->set([ 'timeout' => 5 ]);
        $client->setMethod('POST');
        $client->setData(json_encode([
            'channel' => $channel,
            'message' => $message,
        ]));
        $client->execute('/conveyor/message');
    }

    private function prepareLogger(): void
    {
        $this->logger = new Logger(name: 'jacked-server-log');
        if ($this->logPath !== null) {
            $this->logger->pushHandler(new StreamHandler(
                stream: $this->logPath,
                level: $this->logLevel,
            ));
        } else {
            $this->logger->pushHandler(new EchoHandler(
                level: $this->logLevel
            ));
        }
    }

    private function sendResponse(
        Response $response,
        JackedResponse $jackedResponse,
        callable $streamCallback = null
    ): void {
        if (!empty($jackedResponse->getError())) {
            $this->eventDispatcher->dispatch(new JackedRequestError(
                status: 500,
                headers: $jackedResponse->getHeaders(),
                error: $jackedResponse->getError(),
            ));
            $response->status(500);
            $response->write($jackedResponse->getError());
            $response->end();
            return;
        }

        // prepare response info
        $headers = $jackedResponse->getHeaders();
        $status = $jackedResponse->getStatus();
        $body = $jackedResponse->getBody();
        if ($status[0] >= 400 &&  $status[0] < 600) {
            $this->eventDispatcher->dispatch(new JackedRequestError(
                status: $status[0],
                headers: $headers,
                error: $jackedResponse->getError(),
            ));
        } else {
            $this->eventDispatcher->dispatch(new JackedRequestFinished(
                status: $status[0],
                headers: $headers,
                body: $body,
            ));
        }

        // header
        foreach ($headers as $headerKey => $headerValue) {
            try {
                $response->header($headerKey, $headerValue);
            } catch (Throwable $e) {
                $this->print(
                    message: 'Error at header space:' . $e->getMessage(),
                    type: 'error',
                );

                $this->logger->error('Server Error', [
                    'headers' => $headers,
                    'status' => $status,
                    'error' => $e->getMessage(),
                    'headerKey' => $headerKey,
                    'headerValue' => $headerValue,
                ]);
            }
        }

        // status
        $response->status(...$status);

        // content
        if (in_array($status[0], [301, 302, 303, 307])) {
            $response->redirect($headers['Location'][0]);
        } elseif ($streamCallback !== null) {
            $streamCallback($response);
            $response->end();
        } elseif (!empty($body)) {
            $response->write($body);
        } else {
            $response->end();
        }
    }

    /**
     * @param bool $ssl
     * @return OpenSwooleConfig
     */
    private function getServerConfig(): OpenSwooleConfig
    {
        $camelCasedSettings = [];
        foreach (
            array_merge(Config::get('openswoole-server-settings', [
            // base settings
            'document_root' => $this->publicDocumentRoot, // @phpstan-ignore-line
            'enable_static_handler' => true,
            'static_handler_locations' => [ '/imgs', '/css' ],

            // reactor and workers
            'reactor_num' => $this->reactorNum,
            'worker_num' => $this->workerNum,

            // timeout
            'max_request_execution_time' => $this->timeout,
            ]), ($this->ssl ? [
                'ssl_cert_file' => $this->sslCertFile,
                'ssl_key_file' => $this->sslKeyFile,
                'open_http_protocol' => true,
            ] : [])) as $key => $value
        ) {
            $camelCasedSettings[Str::camel($key)] = $value;
        }

        return OpenSwooleConfig::from($camelCasedSettings);
    }

    /**
     * @param string $message
     * @param string $type Possible values: 'info', 'warning', 'error', 'writeln'
     * @return void
     * @throws RedirectException
     */
    private function print(string $message, string $type = 'info'): void
    {
        if (!in_array($type, ['success', 'info', 'warning', 'error', 'writeln'])) {
            $this->logger->error('Invalid type for server echo: ' . $type);
            echo 'Message: ' . $message . PHP_EOL;
            return;
        }

        if ($this->output instanceof OutputStyle) {
            $this->output->{$type}($message);
        } else {
            echo $message . PHP_EOL;
        }
    }
}
