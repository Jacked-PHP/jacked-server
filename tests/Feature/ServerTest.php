<?php

namespace Tests\Feature;

use JackedPhp\JackedServer\Helpers\Config;
use OpenSwoole\Coroutine;
use OpenSwoole\Coroutine\Http\Client as CoroutineHttpClient;
use OpenSwoole\Process;
use Tests\TestCase;

class ServerTest extends TestCase
{
    public function test_can_get_base_input()
    {
        $serverPid = $this->startServer();
        $status = null;
        $outcome = '';

        Coroutine::run(function () use (&$outcome, &$status) {
            $client = new CoroutineHttpClient('127.0.0.1', Config::get('port'));
            $client->execute('/');
            $status = $client->getStatusCode();
            $outcome = $client->getBody();
        });

        $expected = 'Hello World!';
        $this->assertEquals(
            expected: $expected,
            actual: $outcome,
            message: 'Response body is not as expected: ' . $outcome . ' !== ' . $expected,
        );
        $this->assertEquals(200, $status);

        Process::kill($serverPid);
    }

    public function test_can_send_post()
    {
        $serverPid = $this->startServer();
        $status = null;
        $outcome = '';
        $expectedData = json_encode(['data' => 'test']);

        Coroutine::run(function () use (&$outcome, &$status, $expectedData) {
            $client = new CoroutineHttpClient('127.0.0.1', Config::get('port'));
            $client->setMethod('POST');
            $client->setHeaders([
                'Content-Type' => 'application/json',
            ]);
            $client->setData($expectedData);
            $client->execute('/');
            $status = $client->getStatusCode();
            $outcome = $client->getBody();
        });

        $this->assertEquals(
            expected: $expectedData,
            actual: $outcome,
            message: 'Response body is not as expected: ' . $outcome . ' !== ' . $expectedData,
        );
        $this->assertEquals(200, $status);

        Process::kill($serverPid);
    }

    public function test_can_send_form()
    {
        $serverPid = $this->startServer();
        $status = null;
        $outcome = '';
        $expectedData = ['data' => 'test'];

        Coroutine::run(function () use (&$outcome, &$status, $expectedData) {
            $client = new CoroutineHttpClient('127.0.0.1', Config::get('port'));
            $client->setMethod('POST');
            $client->setHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]);
            $client->setData($expectedData);
            $client->execute('/');
            $status = $client->getStatusCode();
            $outcome = $client->getBody();
        });

        $this->assertEquals(
            expected: json_encode($expectedData),
            actual: $outcome,
            message: 'Response body is not as expected: ' . $outcome . ' !== ' . json_encode($expectedData),
        );
        $this->assertEquals(200, $status);

        Process::kill($serverPid);
    }
}
