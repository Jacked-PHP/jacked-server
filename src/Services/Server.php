<?php

namespace JackedPhp\JackedServer\Services;

use Conveyor\Conveyor;
use Conveyor\ConveyorServer;
use Conveyor\Events\MessageReceivedEvent;
use Conveyor\Events\PreServerStartEvent;
use Conveyor\Events\ServerStartedEvent;
use Conveyor\Persistence\Abstracts\GenericPersistence;
use Exception;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JackedPhp\JackedServer\Events\JackedRequestError;
use JackedPhp\JackedServer\Events\JackedRequestFinished;
use JackedPhp\JackedServer\Events\JackedServerStarted;
use JackedPhp\JackedServer\Exceptions\RedirectException;
use JackedPhp\JackedServer\Services\Response as JackedResponse;
use JackedPhp\JackedServer\Services\Traits\HttpSupport;
use JackedPhp\JackedServer\Services\Traits\WebSocketSupport;
use Kanata\LaravelBroadcaster\Models\Token;
use OpenSwoole\Constant;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Server as OpenSwooleBaseServer;
use OpenSwoole\Util;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\WebSocket\Server as OpenSwooleServer;
use Psr\Log\LoggerInterface;

class Server
{
    use HttpSupport;
    use WebSocketSupport;

    private LoggerInterface $logger;
    private string $logPrefix = 'JackedServer: ';
    private string $inputFile;

    /**
     * @var array <array-key, GenericPersistenceInterface>
     */
    private array $wsPersistence;

    /**
     * We elect the first worker to do the refresh for
     * WebSocket functionalities.
     *
     * @var string
     */
    private string $executionHolder = 'jacked-server-execution';

    /**
     * @param string|null $host
     * @param int|null $port
     * @param string|null $inputFile
     * @param string|null $documentRoot
     * @param string|null $publicDocumentRoot
     * @param OutputStyle|null $output
     * @param array<GenericPersistence>|null $this->wsPersistenceOptions
     * @param Manager|null $manager
     */
    public function __construct(
        private readonly ?string $host = null,
        private ?int $port = null,
        ?string $inputFile = null,
        private readonly ?string $documentRoot = null,
        private readonly ?string $publicDocumentRoot = null,
        private readonly ?OutputStyle $output = null,
        private ?array $wsPersistenceOptions = null,
        private ?Manager $manager = null,
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

        if (Storage::exists($this->executionHolder)) {
            Storage::delete($this->executionHolder);
        }
    }

    public function run(): void
    {
        $ssl = config('jacked-server.ssl-enabled', false);
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
        $host = $this->host ?? config('jacked-server.host', '0.0.0.0');
        $primaryPort = $ssl ? config('jacked-server.ssl-port', 443) : config('jacked-server.port', 8080);

        if (false === config('jacked-server.websocket.enabled', true)) {
            // TODO: craft HTTP only server
            throw new Exception('WebSockets are not enabled! HTTP only servers are not available yet by Jacked Server.');
        }

        $this->wsPersistence = Conveyor::defaultPersistence();

        ConveyorServer::start(
            host: $host,
            port: $this->port ?? $primaryPort,
            mode: config('jacked-server.server-type', OpenSwooleBaseServer::POOL_MODE),
            ssl: $ssl ? Constant::SOCK_TCP | Constant::SSL : Constant::SOCK_TCP,
            serverOptions: $this->getServerConfig($ssl),
            eventListeners: [
                ConveyorServer::EVENT_SERVER_STARTED => fn(ServerStartedEvent $event) => $this->handleStart($event->server),
                ConveyorServer::EVENT_PRE_SERVER_START => function (PreServerStartEvent $event) use ($ssl) {
                    if ($ssl) {
                        $event->server->listen(
                            $event->server->host,
                            config('jacked-server.port', 8080),
                            Constant::SOCK_TCP,
                        )->on('request', [$this, 'sslRedirectRequest']);
                    }
                    $event->server->on('request', [$this, 'handleRequest']);
                    $event->server->on('handshake', [$this, 'handleWsHandshake']);
                    $event->server->on('open', [$this, 'handleWsOpen']);
                },
                ConveyorServer::EVENT_MESSAGE_RECEIVED => [$this, 'handleWsMessage'],
            ],
            persistence: $this->wsPersistence,
        );
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
            $broadcaster = rescue(fn() => Broadcast::driver('conveyor'));
            $wsAuth = config('jacked-server.websocket.broadcaster');

            parse_str(Arr::get($request->server, 'query_string') ?? '', $query);
            $token = Arr::get($query, 'token');

            if ($wsAuth && null === $token) {
                throw new Exception('Not authorized!');
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
        } catch (Exception $e) {
            file_put_contents('/var/www/laravel-example/packages/jacked-php/jacked-server/test.txt', json_encode([
                'error' => $e->getMessage(),
                'this' => get_class($this),
            ]) . PHP_EOL, FILE_APPEND);
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

    public function handleWsMessage(MessageReceivedEvent $event): void
    {
        $parsedData = json_decode($event->data);

        if (!Storage::exists($this->executionHolder)) {
            Storage::put($this->executionHolder, $event->server->getWorkerId());
        }

        $this->logger->info($this->logPrefix . ' Message received from ' . $parsedData->fd);
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
        try {
            [ $requestOptions, $content ] = $this->gatherRequestInfo($request);
        } catch (RedirectException $e) {
            $response->status(302);
            $response->redirect($e->getMessage());
            return;
        }

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

    private function getServerConfig(bool $ssl): array
    {
        return array_merge(config('jacked-server.openswoole-server-settings', [
            'document_root' => $this->publicDocumentRoot ?? public_path(),
            'enable_static_handler' => true,
            'static_handler_locations' => [ '/imgs', '/css' ],
            // reactor and workers
            'reactor_num' => Util::getCPUNum() + 2,
            'worker_num' => Util::getCPUNum() + 2,
            // timeout
            'max_request_execution_time' => config('jacked-server.timeout', 60),
        ]), ($ssl ? [
            'ssl_cert_file' => config('jacked-server.ssl-cert-file'),
            'ssl_key_file' => config('jacked-server.ssl-key-file'),
            'open_http_protocol' => true,
        ] : []));
    }
}
