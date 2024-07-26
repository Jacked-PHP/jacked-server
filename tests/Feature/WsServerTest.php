<?php

namespace Feature;

use JackedPhp\JackedServer\Helpers\Config;
use OpenSwoole\Atomic;
use OpenSwoole\Process;
use Tests\TestCase;
use Kanata\ConveyorServerClient\Client;

class WsServerTest extends TestCase
{
    public function test_can_get_base_input()
    {
        $serverPid = $this->startServer();

        $atomic = new Atomic();

        $process1 = new Process(function (Process $worker) use ($atomic) {
            $client = new Client([
                'protocol' => 'ws',
                'uri' => '127.0.0.1',
                'port' => Config::get('port'),
                'channel' => 'test-channel',
                'onReadyCallback' => function ($client) use ($atomic, $worker) {
                    while ($atomic->get() < 1) {
                        usleep(500000); // 0.5 sec
                    }
                    $client->send('Test Message 1');
                    usleep(100000); // 0.1 sec
                    $worker->close();
                },
            ]);
            $client->connect();
        });
        $process1Pid = $process1->start();

        $process2 = new Process(function (Process $worker) use ($atomic) {
            $client = new Client([
                'protocol' => 'ws',
                'uri' => '127.0.0.1',
                'port' => Config::get('port'),
                'channel' => 'test-channel',
                'onMessageCallback' => function (Client $currentClient, string $message) use ($worker) {
                    $parsedMessage = json_decode($message, true);
                    $worker->write('Message received: ' . $parsedMessage['data']);
                    $worker->close();
                },
                'onReadyCallback' => function ($client) use ($atomic) {
                    $atomic->add();
                },
            ]);
            $client->connect();
        });
        $process2Pid = $process2->start();

        $result = $process2->read();
        usleep(300000); // 0.1 sec

        $this->assertEquals('Message received: Test Message 1', $result);

        Process::kill($process1Pid);
        Process::kill($process2Pid);
        Process::kill($serverPid);
    }
}
