<?php

namespace Tests\Feature;

use App\Models\User;
use co;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Kanata\LaravelBroadcaster\ConveyorServiceProvider;
use Kanata\LaravelBroadcaster\Services\JwtToken;
use Laravel\Sanctum\NewAccessToken;
use OpenSwoole\Coroutine;
use OpenSwoole\Coroutine\Channel;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use Tests\Feature\Traits\ServerTrait;
use WebSocket\Client;
use WebSocket\ConnectionException;
use function Orchestra\Testbench\artisan;

class WebSocketServerTest extends TestCase
{
    use WithWorkbench;
    use ServerTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpLaravel();
    }

    /**
     * Workbench customization.
     * @return array
     */
    public function ignorePackageDiscoveriesFrom(): array
    {
        return [];
    }

    private function addBroadcaster(): void
    {
        $this->app->register(ConveyorServiceProvider::class);
        Config::set('jacked-server.websocket.broadcaster', true);
        Config::set('broadcasting.connections.conveyor.driver', 'conveyor');
    }

    private function prepareDatabase(): void
    {
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql', array_merge(
            Config::get('database.connections.mysql'),
            [
                'port' => 33061,
                'database' => 'laravel',
                'username' => 'root',
                'password' => 'password',
            ],
        ));

        artisan($this, 'migrate:fresh', ['--database' => 'mysql']);

        Config::set('database.connections.socket-conveyor', Config::get('database.connections.mysql'));
    }

    private function getWsClient(?string $address = null, ?string $token = null): Client
    {
        $address = $address ?? 'ws://127.0.0.1:' . $this->port . '?token=';

        return new Client($address . ($token ?? JwtToken::create(
            name: 'test-1',
            userId: User::factory()->create()->id,
            expire: 60,
            useLimit: 1,
        )->token), [
            'timeout' => 10,
        ]);
    }

    /**
     * @param NewAccessToken $token
     * @param string|null $channel
     * @return string Token valid for Ws Connection
     */
    private function getWsToken(NewAccessToken $token, ?string $channel = null): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken,
            'Accept' => 'application/json',
        ])->post('http://127.0.0.1:' . $this->port . '/broadcasting/auth', [
            'channel_name' => $channel,
        ]);
        $this->assertEquals(200, $response->status());

        return $response->json('auth');
    }

    private function failToGetWsToken(): void
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer invalid-token',
            'Accept' => 'application/json',
        ])->post('http://127.0.0.1:' . $this->port . '/broadcasting/auth');

        $this->assertEquals(401, $response->status());
        $this->assertJson(json_encode([
            'message' => 'Unauthenticated.',
        ]), $response->body());
    }

    public function test_can_connect_to_ws_server(): void
    {
        $expectedMessage = 'text-message';

        $this->startServer(
            inputFile: rtrim(__DIR__, '/Feature') . '/Assets/assert-ok.php',
            documentRoot: rtrim(__DIR__, '/Feature') . '/Assets',
        );

        $response = Http::get('http://127.0.0.1:' . $this->port . '/assert-ok.php');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('ok', $response->body());

        $client = new Client('ws://127.0.0.1:' . $this->port);
        $client->text($expectedMessage);
        $result = $client->receive();
        $client->close();
        $this->assertEquals($expectedMessage, json_decode($result, true)['data']);

        $this->tear_server_down();
    }

    public function test_cant_connect_to_protected_ws_server_without_token(): void
    {
        $this->addBroadcaster();

        $this->startServer();

        $client = new Client('ws://127.0.0.1:' . $this->port);
        $result = rescue(fn() => $client->send('test'), 'failed');
        $this->assertEquals('failed', $result);

        $this->tear_server_down();
    }

    public function test_can_connect_to_ws_server_with_default_auth(): void
    {
        $this->addBroadcaster();
        $this->prepareDatabase();
        $expectedMessage = 'text-message';

        $this->startServer();

        $client = $this->getWsClient();
        $client->text($expectedMessage);
        $result = $client->receive();
        $client->close();
        $this->assertEquals($expectedMessage, json_decode($result, true)['data']);

        $this->tear_server_down();
    }

    public function test_can_message_channel(): void
    {
        $expectedMessage = 'text-message';
        $expectedMessage2 = 'text-message-2';
        $channel = 'test-channel';
        $channel2 = 'test-channel-2';

        $this->startServer();

        $address = 'ws://127.0.0.1:' . $this->port;
        $client = new Client($address);
        $client2 = new Client($address);
        $client3 = new Client($address);
        $client4 = new Client($address);
        $client5 = new Client($address);

        // connect to channel
        $client->text(json_encode(['action' => 'channel-connect', 'channel' => $channel]));
        $client2->text(json_encode(['action' => 'channel-connect', 'channel' => $channel]));
        $client3->text(json_encode(['action' => 'channel-connect', 'channel' => $channel]));
        // channel2
        $client4->text(json_encode(['action' => 'channel-connect', 'channel' => $channel2]));
        $client5->text(json_encode(['action' => 'channel-connect', 'channel' => $channel2]));

        // send from client2 and receive at client
        $result = '';
        $result2 = '';
        $result3 = '';
        sleep(1);
        co::run(function () use (
            $client,
            $client2,
            $client3,
            $client4,
            $client5,
            &$result,
            &$result2,
            &$result3,
            $expectedMessage,
            $expectedMessage2,
        ) {
            $chan = new Channel(1);
            $chan2 = new Channel(1);
            $chan3 = new Channel(1);

            go(function () use ($client2, $client4, &$result, $expectedMessage, $expectedMessage2, $chan) {
                $client2->text(json_encode(['action' => 'broadcast-action', 'data' => $expectedMessage]));
                $client4->text(json_encode(['action' => 'broadcast-action', 'data' => $expectedMessage2]));
                $chan->push('done');
            });

            go(function () use ($client, &$result, $chan, $chan2) {
                $chan->pop();
                $result = $client->receive();
                $chan2->push('done');
            });

            go(function () use ($client3, &$result2, $chan2, $chan3) {
                $chan2->pop();
                $result2 = $client3->receive();
                $chan3->push('done');
            });

            go(function () use ($client5, &$result3, $chan3) {
                $chan3->pop();
                $result3 = $client5->receive();
            });
        });

        $client->close();
        $client2->close();
        $client3->close();
        $client4->close();
        $client5->close();

        $this->assertEquals($expectedMessage, json_decode($result, true)['data']);
        $this->assertEquals($expectedMessage, json_decode($result2, true)['data']);
        $this->assertEquals($expectedMessage2, json_decode($result3, true)['data']);

        $this->tear_server_down();
    }

    public function test_can_message_protected_channel(): void
    {
        $this->addBroadcaster();
        $this->prepareDatabase();

        $expectedMessage = 'text-message';
        $expectedMessage2 = 'text-message-2';
        $channel = 'test-channel';
        $channel2 = 'test-channel-2';

        $this->startServer();

        $client = $this->getWsClient();
        $client2 = $this->getWsClient();
        $client3 = $this->getWsClient();
        $client4 = $this->getWsClient();
        $client5 = $this->getWsClient();

        // connect to channel
        $client->text(json_encode(['action' => 'channel-connect', 'channel' => $channel]));
        $client2->text(json_encode(['action' => 'channel-connect', 'channel' => $channel]));
        $client3->text(json_encode(['action' => 'channel-connect', 'channel' => $channel]));
        // channel2
        $client4->text(json_encode(['action' => 'channel-connect', 'channel' => $channel2]));
        $client5->text(json_encode(['action' => 'channel-connect', 'channel' => $channel2]));

        // send from client2 and receive at client
        $result = '';
        $result2 = '';
        $result3 = '';
        sleep(1);
        co::run(function () use (
            $client,
            $client2,
            $client3,
            $client4,
            $client5,
            &$result,
            &$result2,
            &$result3,
            $expectedMessage,
            $expectedMessage2,
        ) {
            $chan = new Channel(1);
            $chan2 = new Channel(1);
            $chan3 = new Channel(1);

            go(function () use ($client2, $client4, &$result, $expectedMessage, $expectedMessage2, $chan) {
                $client2->text(json_encode(['action' => 'broadcast-action', 'data' => $expectedMessage]));
                $client4->text(json_encode(['action' => 'broadcast-action', 'data' => $expectedMessage2]));
                $chan->push('done');
            });

            go(function () use ($client, &$result, $chan, $chan2) {
                $chan->pop();
                $result = $client->receive();
                $chan2->push('done');
            });

            go(function () use ($client3, &$result2, $chan2, $chan3) {
                $chan2->pop();
                $result2 = $client3->receive();
                $chan3->push('done');
            });

            go(function () use ($client5, &$result3, $chan3) {
                $chan3->pop();
                $result3 = $client5->receive();
            });
        });

        $client->close();
        $client2->close();
        $client3->close();
        $client4->close();
        $client5->close();

        $this->assertEquals($expectedMessage, json_decode($result, true)['data']);
        $this->assertEquals($expectedMessage, json_decode($result2, true)['data']);
        $this->assertEquals($expectedMessage2, json_decode($result3, true)['data']);

        $this->tear_server_down();
    }

    public function test_can_authenticate_with_laravel_broadcaster(): void
    {
        $channel = 'private-test-channel';
        $expectedMessage = 'test message';

        $this->addBroadcaster();
        $this->prepareDatabase();

        $user = User::factory()->create();
        $token = $user->createToken('test');
        $token2 = $user->createToken('test2');

        $this->startServer();

        $client = $this->getWsClient(
            token: $this->getWsToken($token, $channel),
        );
        $client2 = $this->getWsClient(
            token: $this->getWsToken($token2, $channel),
        );

        $client->text(json_encode(['action' => 'channel-connect', 'channel' => $channel]));
        $client2->text(json_encode(['action' => 'channel-connect', 'channel' => $channel]));

        $result = '';
        sleep(1);
        co::run(function () use (
            $client,
            $client2,
            &$result,
            $expectedMessage,
        ) {
            go(function () use ($client2, &$result, $expectedMessage) {
                $client2->text(json_encode(['action' => 'broadcast-action', 'data' => $expectedMessage]));
            });

            go(function () use ($client, &$result) {
                Coroutine::sleep(1);
                $result = $client->receive();
            });
        });

        $client->close();

        $this->assertEquals($expectedMessage, json_decode($result, true)['data']);

        $this->tear_server_down();
    }

    public function test_cant_authenticate_with_laravel_broadcaster_with_wrong_credentials(): void
    {
        $this->addBroadcaster();
        $this->prepareDatabase();

        $this->startServer();

        $this->failToGetWsToken();

        $this->tear_server_down();
    }

    public function test_cant_connect_with_invalid_credentials(): void
    {
        $channel = 'test-channel';

        $this->addBroadcaster();
        $this->prepareDatabase();

        $token = User::factory()
            ->create()
            ->createToken('test');
        $token2 = 'invalid-token';

        $this->startServer();

        $client = $this->getWsClient(
            token: $this->getWsToken($token, $channel),
        );
        $client2 = $this->getWsClient(
            token: $token2,
        );

        try {
            $client->text(json_encode(['action' => 'channel-connect', 'channel' => $channel]));
        } catch (ConnectionException $e) {
            $this->assertTrue(false, 'Client 1 failed to connect to channel');
        }

        try {
            $client2->text(json_encode(['action' => 'channel-connect', 'channel' => $channel]));
        } catch (ConnectionException $e) {
            $this->assertTrue(true);
            $this->assertStringContainsString('401 Unauthorized', $e->getMessage());
        }

        $this->tear_server_down();
    }
}
