<?php

namespace Tests\Feature;

use JackedPhp\JackedServer\Helpers\Config;
use OpenSwoole\Atomic;
use OpenSwoole\Coroutine;
use OpenSwoole\Coroutine\Http\Client as CoroutineHttpClient;
use OpenSwoole\Process;
use Tests\TestCase;
use Kanata\ConveyorServerClient\Client;
use Throwable;

class WsServerTest extends TestCase
{
    /** @var array<int>  */
    protected array $processesPids;

    /**
     * @after
     * @return void
     */
    public function stopProcesses(): void
    {
        foreach ($this->processesPids as $pid) {
            Process::kill($pid, SIGKILL);
        }
        sleep(3);
    }

    protected function startWsClient(
        string $configFile,
        ?callable $onReadyCallback = null,
        ?callable $onMessageCallback = null,
        string $query = '',
        int $timeout = -1,
        ?int $processTimeout = null,
    ): Process {
        $process = new Process(
            callback: function (Process $worker) use (
                $configFile,
                $onReadyCallback,
                $onMessageCallback,
                $query,
                $timeout,
            ) {
                $client = new Client([
                    'protocol' => 'ws',
                    'uri' => '127.0.0.1',
                    'port' => Config::get('port', configFile: $configFile),
                    'query' => $query,
                    'channel' => 'test-channel',
                    'onReadyCallback' => fn(Client $client) => $onReadyCallback ? $onReadyCallback(
                        client: $client,
                        worker: $worker,
                    ) : null,
                    'onMessageCallback' => fn(Client $client, string $message) => $onMessageCallback ? $onMessageCallback(
                        client: $client,
                        worker: $worker,
                        message: $message,
                    ) : null,
                    'onReconnectionCallback' => function (Client $client, Throwable $e) use ($worker) {
                        $worker->write(json_encode([
                            'status' => 'Reconnection due to failure Reached!',
                            'rawMessage' => $e->getMessage(),
                        ]));
                    },
                    'timeout' => $timeout,
                ]);
                $client->connect();
            },
            redirectStdIO: true,
            enableCoroutine: false,
        );
        $this->processesPids[] = $process->start();

        if (null !== $processTimeout) {
            $process->setTimeout($processTimeout);
        }

        return $process;
    }

    protected function getToken(string $configFile): string
    {
        Coroutine::run(function () use (&$outcome, &$status, $configFile) {
            $client = new CoroutineHttpClient('127.0.0.1', Config::get('port', configFile: $configFile));
            $client->setMethod('POST');
            $client->setHeaders([
                'Authorization' => 'Bearer ' . Config::get('websocket.token', configFile: $configFile),
            ]);
            $client->setData([
                'channel_name' => 'test-channel',
            ]);
            $client->execute('/broadcasting/auth');
            $status = $client->getStatusCode();
            $outcome = $client->getBody();
        });

        $parsedOutcome = json_decode($outcome, true);
        $this->assertArrayHasKey('auth', $parsedOutcome);
        $this->assertEquals(200, $status);

        return $parsedOutcome['auth'];
    }

    public function test_can_broadcast_message()
    {
        $configFile = ROOT_DIR . '/config/jacked-server.php';

        // @throws Exception
        $this->processesPids[] = $this->startServer(configFile: $configFile);

        $atomic = new Atomic();

        $this->startWsClient(
            onReadyCallback: function ($client, $worker) use ($atomic) {
                while ($atomic->get() < 1) {
                    usleep(500000); // 0.5 sec
                }
                $client->send('Test Message 1');
                usleep(100000); // 0.1 sec
                $client->close();
                $worker->close();
            },
            configFile: $configFile,
        );

        $process2 = $this->startWsClient(
            onReadyCallback: function (Client $client, Process $worker) use ($atomic) {
                $atomic->add();
            },
            onMessageCallback: function (Client $client, Process $worker, string $message) use ($atomic) {
                $parsedMessage = json_decode($message, true);
                $worker->write('Message received: ' . $parsedMessage['data']);
                $client->close();
                $worker->close();
            },
            configFile: $configFile,
        );

        $result = $process2->read();
        usleep(300000); // 0.3 sec

        $this->assertEquals('Message received: Test Message 1', $result);
    }

    public function test_can_authenticate_remote_server_at_handshake(): void
    {
        $configFile = ROOT_DIR . '/ws-auth/jacked-server-with-ws-auth.php';

        // @throws Exception
        $this->processesPids[] = $this->startServer(configFile: $configFile);

        $token1 = $this->getToken($configFile);
        $token2 = $this->getToken($configFile);
        usleep(300000); // 0.3 sec

        $atomic = new Atomic();

        $this->startWsClient(
            onReadyCallback: function ($client, $worker) use ($atomic) {
                while ($atomic->get() < 1) {
                    usleep(500000); // 0.5 sec
                }
                $client->send('Test Message 1');
                usleep(100000); // 0.1 sec
                $client->close();
                $worker->close();
            },
            query: '?token=' . $token1,
            configFile: $configFile,
        );

        $process2 = $this->startWsClient(
            onReadyCallback: function (Client $client, Process $worker) use ($atomic) {
                $atomic->add();
            },
            onMessageCallback: function (Client $client, Process $worker, string $message) use ($atomic) {
                $parsedMessage = json_decode($message, true);
                $worker->write('Message received: ' . $parsedMessage['data']);
                $client->close();
                $worker->close();
            },
            query: '?token=' . $token2,
            configFile: $configFile,
        );

        $result = $process2->read();
        usleep(300000); // 0.3 sec

        $this->assertEquals('Message received: Test Message 1', $result);
    }

    public function test_fail_to_authenticate_remote_server_at_handshake(): void
    {
        $configFile = ROOT_DIR . '/ws-auth/jacked-server-with-ws-auth-2.php';

        // @throws Exception
        $this->processesPids[] = $this->startServer(
            configFile: $configFile,
        );

        $process = $this->startWsClient(
            onReadyCallback: function ($client, $worker) {
                $worker->write('Test Message 1');
                usleep(100000); // 0.1 sec
                $client->close();
                $worker->close();
            },
            query: '?token=invalid-token',
            timeout: 1,
            processTimeout: 2,
            configFile: $configFile,
        );

        $result = $process->read();

        $parsedResult = json_decode($result, true);
        $this->assertEquals(
            expected: 'Reconnection due to failure Reached!',
            actual: $parsedResult['status'],
        );
        $this->assertStringContainsString(
            needle: '401',
            haystack: $parsedResult['rawMessage'],
        );
    }
}
