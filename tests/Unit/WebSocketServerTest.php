<?php

namespace Unit;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use WebSocket\Client;

class WebSocketServerTest extends TestCase
{
    public function test_can_connect_to_ws_server()
    {
        $expectedMessage = 'text-message';

        $this->startServer(
            rtrim(__DIR__, '/Unit') . '/Assets/assert-ok.php',
            rtrim(__DIR__, '/Unit') . '/Assets',
        );

        $response = Http::get('http://127.0.0.1:' . $this->port . '/assert-ok.php');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('ok', $response->body());

        $client = new Client('ws://127.0.0.1:' . $this->port);
        $client->text($expectedMessage);
        $result = $client->receive();
        $client->close();
        $this->assertEquals($expectedMessage, json_decode($result, true)['data']);
    }

    public function test_can_connect_to_ws_server_with_default_auth()
    {
        $expectedMessage = 'text-message';

        $this->startServer(
            rtrim(__DIR__, '/Unit') . '/Assets/assert-ok.php',
            rtrim(__DIR__, '/Unit') . '/Assets',
        );

        $response = Http::get('http://127.0.0.1:' . $this->port . '/assert-ok.php');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('ok', $response->body());

        $client = new Client('ws://127.0.0.1:' . $this->port);
        $client->text($expectedMessage);
        $result = $client->receive();
        $client->close();
        $this->assertEquals($expectedMessage, json_decode($result, true)['data']);
    }
}
