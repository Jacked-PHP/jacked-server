<?php

namespace JackedPhp\JackedServer\Services;

use Carbon\Carbon;
use Conveyor\Constants;
use Conveyor\ConveyorServer;
use Conveyor\Events\MessageReceivedEvent;
use Conveyor\Events\PreServerStartEvent;
use Conveyor\Events\ServerStartedEvent;
use Conveyor\Helpers\Arr;
use Conveyor\SubProtocols\Conveyor\Broadcast;
use Conveyor\SubProtocols\Conveyor\Conveyor;
use Conveyor\SubProtocols\Conveyor\Persistence\Interfaces\GenericPersistenceInterface;
use Exception;
use Hook\Filter;
use Illuminate\Auth\Access\AuthorizationException;
use JackedPhp\JackedServer\Events\JackedRequestError;
use JackedPhp\JackedServer\Events\JackedRequestFinished;
use JackedPhp\JackedServer\Events\JackedServerStarted;
use JackedPhp\JackedServer\Exceptions\RedirectException;
use JackedPhp\JackedServer\Helpers\Config;
use JackedPhp\JackedServer\Services\Logger\EchoHandler;
use JackedPhp\JackedServer\Services\Response as JackedResponse;
use JackedPhp\JackedServer\Services\Traits\HttpSupport;
use JackedPhp\JackedServer\Services\Traits\WebSocketSupport;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use OpenSwoole\Constant;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Server as OpenSwooleBaseServer;
use OpenSwoole\WebSocket\Server as OpenSwooleServer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Throwable;

class Server
{
    use HttpSupport;
    use WebSocketSupport;

    private LoggerInterface $logger;
    private string $logPrefix = 'JackedServer: ';
    private string $inputFile;

    /**
     * @param string|null $host
     * @param int|null $port
     * @param string|null $inputFile
     * @param string|null $documentRoot
     * @param string|null $publicDocumentRoot
     * @param SymfonyStyle|null $output
     * @param array<GenericPersistenceInterface> $wsPersistence
     * @param EventDispatcher $eventDispatcher
     * @param string $logPath
     * @param int $logLevel
     */
    public function __construct(
        private readonly ?string $host = null,
        private ?int $port = null,
        ?string $inputFile = null,
        private readonly ?string $documentRoot = null,
        private readonly ?string $publicDocumentRoot = null,
        private readonly ?SymfonyStyle $output = null,
        private array $wsPersistence = [],
        private ?EventDispatcher $eventDispatcher = null,
        string $logPath = null,
        int $logLevel = null,
    ) {
        $this->inputFile = $inputFile ?? Config::get(
            'input-file',
            ROOT_DIR . '/index.php',
        );

        $this->logger = new Logger(name: 'jacked-server-log');
        if ($logPath !== null) {
            $this->logger->pushHandler(new StreamHandler(
                stream: $logPath ?? Config::get('log.stream', ROOT_DIR . '/jacked-server.log'),
                level: $logLevel ?? Config::get('log.level', Level::Warning),
            ));
        } else {
            $this->logger->pushHandler(new EchoHandler(
                level: $logLevel ?? Config::get('log.level', Level::Warning)
            ));
        }

        $this->eventDispatcher = $eventDispatcher ?? new EventDispatcher;
    }

    /**
     * @throws Exception
     */
    public function run(): void
    {
        $ssl = Config::get('ssl-enabled', false);
        if (null !== $this->port && $ssl) {
            $this->logger->warning(
                'SSL is enabled, but a port was specified. ' .
                'The port will be ignored in favor of the SSL port.',
            );
            $this->output->warning(
                'SSL is enabled, but a port was specified. ' .
                'The port will be ignored in favor of the SSL port.',
            );
            $this->port = null;
        }
        $host = $this->host ?? Config::get('host', '0.0.0.0');
        $primaryPort = $ssl ? Config::get('ssl-port', 443) : Config::get('port', 8080);

        $this->wsPersistence = array_merge(
            Conveyor::defaultPersistence(),
            $this->wsPersistence,
        );

        Filter::addFilter(Constants::FILTER_REQUEST_HANDLER, fn() => [$this, 'handleRequest']);

        ConveyorServer::start(
            host: $host,
            port: $this->port ?? $primaryPort,
            mode: Config::get('server-type', OpenSwooleBaseServer::POOL_MODE),
            ssl: $ssl ? Constant::SOCK_TCP | Constant::SSL : Constant::SOCK_TCP,
            serverOptions: $this->getServerConfig($ssl),
            conveyorOptions: array_merge(Config::get('conveyor-options', []), [
                Constants::USE_ACKNOWLEDGMENT => true, // required for broadcasting
            ]),
            eventListeners: [
                Constants::EVENT_SERVER_STARTED => fn(ServerStartedEvent $event) =>
                $this->handleStart($event->server),
                Constants::EVENT_PRE_SERVER_START => function (PreServerStartEvent $event) use ($ssl) {
                    if ($ssl) {
                        $event->server->listen(
                            $event->server->host,
                            Config::get('port', 8080),
                            Constant::SOCK_TCP,
                        )->on('request', [$this, 'sslRedirectRequest']);
                    }
                    $event->server->on('handshake', [$this, 'handleWsHandshake']);
                },
                Constants::EVENT_MESSAGE_RECEIVED => [$this, 'handleWsMessage'],
            ],
            persistence: $this->wsPersistence,
        );
    }

    public function handleWsHandshake(Request $request, Response $response): bool
    {
        if (Config::get('websocket.enabled', false) !== true) {
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
            // TODO: adjust this to reach out to laravel
            try {
                $broadcaster = Broadcast::driver('conveyor');
            } catch (Exception $e) {
                // silence...
            }
            $wsAuth = Config::get('websocket.broadcaster');

            parse_str(Arr::get($request->server, 'query_string') ?? '', $query);
            $token = Arr::get($query, 'token');

            if ($wsAuth && null === $token) {
                throw new AuthorizationException('WebSocket Connection Not authorized!');
            }

            if ($wsAuth && null !== $broadcaster) {
                $user = Token::byToken($token)->first()?->user;
                $broadcaster->validateConnection($token);
                $broadcaster->associateUser(
                    fd: $request->fd,
                    user: $user,
                    assocPersistence: Arr::get($this->wsPersistence, 'user-associations'),
                );
            }
        } catch (AuthorizationException $e) {
            $this->logger->info($this->logPrefix . $e->getMessage());
            $response->status(401);
            $response->end($e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->logger->error($this->logPrefix . 'Websocket Handshake Exception: ' . $e->getMessage());
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

        if ($this->output instanceof OutputStyle) {
            $this->output->success($message);
        } else {
            echo $message . PHP_EOL;
        }

        $this->eventDispatcher->dispatch(new JackedServerStarted(
            host: $server->host,
            port: $server->port,
        ));
    }

    public function handleRequest(Request $request, Response $response): void
    {
        /**
         * Description: This is a filter for requests proxy.
         * Name: jacked_proxy_request
         * Params:
         *   - $proxyRequest: bool
         *   - $request: Request
         * Returns: bool
         */
        $proxyRequest = Filter::applyFilters(
            'jacked_proxy_request',
            Config::get('proxy.enabled', false),
            $request,
        );

        if ($proxyRequest) {
            /**
             * Description: This is a filter for proxy host.
             * Name: jacked_proxy_host
             * Params:
             *   - $proxyHost: string
             *   - $request: Request
             * Returns: string
             */
            $proxyHost = Filter::applyFilters(
                'jacked_proxy_host',
                Config::get('proxy.host', '127.0.0.1'),
                $request,
            );

            /**
             * Description: This is a filter for proxy port.
             * Name: jacked_proxy_port
             * Params:
             *   - $proxyPort: int
             *   - $request: Request
             * Returns: int
             */
            $proxyPort = Filter::applyFilters(
                'jacked_proxy_port',
                Config::get('proxy.port', 3000),
                $request,
            );

            /**
             * Description: This is a filter for proxy allowed headers.
             * Name: jacked_proxy_allowed_headers
             * Params:
             *   - $proxyAllowedHeaders: array<array-key, string>
             *   - $request: Request
             * Returns: array<array-key, string>
             */
            $proxyAllowedHeaders = Filter::applyFilters(
                'jacked_proxy_allowed_headers',
                Config::get('proxy.allowed-headers', [
                    'content-type',
                ]),
                $request,
            );

            $this->proxyRequest(
                request: $request,
                response: $response,
                host: $proxyHost,
                port: $proxyPort,
                allowedHeaders: $proxyAllowedHeaders,
            );
            return;
        }

        $this->output->writeln(
            'Request received: '
            . $request->server['request_method'] . ' '
            . $request->server['request_uri']
        );

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

    private function sendResponse(Response $response, JackedResponse $jackedResponse, callable $streamCallback = null): void
    {
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
                $this->output->error(
                    'Error at header space:' . $e->getMessage(),
                );
                $this->logger->error('Server Error', [
                    'headers' => $headers,
                    'status' => $status,
                    'error' => $e->getMessage(),
                    'headerKey' => $headerKey,
                    'headerValue' =>$headerValue,
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

    private function getServerConfig(bool $ssl): array
    {
        return array_merge(Config::get('openswoole-server-settings', [

            // base settings
            'document_root' => $this->publicDocumentRoot ?? ROOT_DIR,
            'enable_static_handler' => true,
            'static_handler_locations' => [ '/imgs', '/css' ],

            // reactor and workers
            'reactor_num' => (int) Config::get('reactor-num', 4),
            'worker_num' => (int) Config::get('worker-num', 4),

            // timeout
            'max_request_execution_time' => Config::get('timeout', 60),

        ]), ($ssl ? [
            'ssl_cert_file' => Config::get('ssl-cert-file'),
            'ssl_key_file' => Config::get('ssl-key-file'),
            'open_http_protocol' => true,
        ] : []));
    }
}
