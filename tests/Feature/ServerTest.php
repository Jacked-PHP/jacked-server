<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\Feature\Traits\ServerTrait;
use Tests\TestCase;

class ServerTest extends TestCase
{
    use ServerTrait;

    public function test_can_set_custom_input_file()
    {
        $this->startServer(
            rtrim(__DIR__, '/Feature') . '/Assets/assert-ok.php',
            rtrim(__DIR__, '/Feature') . '/Assets',
        );

        $response = Http::get('http://127.0.0.1:' . $this->port . '/assert-ok.php');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('ok', $response->body());
    }

    public function test_can_run_server()
    {
        // dd($this->laravelPath);
        $this->startServer(
            $this->laravelPath . '/public/index.php',
        );

        $response = Http::get('http://127.0.0.1:' . $this->port);
        $this->assertEquals(200, $response->status());
        $this->assertEquals('ok', $response->body());
    }

    public function test_can_get_csrf_token_via_api_request()
    {
        $this->startServer(
            $this->laravelPath . '/public/index.php',
        );

        $response = Http::get('http://127.0.0.1:' . $this->port . '/get-csrf');
        $this->assertEquals(200, $response->status());
    }

    public function test_can_send_post_to_server_keeping_session()
    {
        $this->startServer(
            $this->laravelPath . '/public/index.php',
        );

        $csrfResponse = Http::withCookies([], '127.0.0.1:' . $this->port)
            ->get('http://127.0.0.1:' . $this->port . '/get-csrf');
        $csrf = $csrfResponse->body();

        $response = Http
            ::withCookies(...$this->getCookiesFromResponse($csrfResponse))
            ->withHeaders([
                'X-CSRF-TOKEN' => $csrf,
                'Content-Type' => 'application/json',
            ])
            ->post('http://127.0.0.1:' . $this->port . '/form', [
                'test' => 'ok',
            ]);

        $this->assertEquals(200, $response->status());
        $this->assertEquals('ok', $response->json('test'));
    }

    public function test_can_get_200()
    {
        $this->startServer(
            $this->laravelPath . '/public/index.php',
        );
        $this->assertEquals(
            200,
            Http::get('http://127.0.0.1:' . $this->port . '/get-200')->status()
        );
    }

    public function test_can_get_302()
    {
        $this->startServer(
            $this->laravelPath . '/public/index.php',
        );
        $this->assertEquals(
            302,
            Http::withOptions(['allow_redirects' => false])
                ->get('http://127.0.0.1:' . $this->port . '/get-302')
                ->status()
        );
    }

    public function test_can_get_400()
    {
        $this->startServer(
            $this->laravelPath . '/public/index.php',
        );
        $this->assertEquals(
            400,
            Http::get('http://127.0.0.1:' . $this->port . '/get-400')->status()
        );
    }

    public function test_can_get_404()
    {
        $this->startServer(
            $this->laravelPath . '/public/index.php',
        );
        $this->assertEquals(
            404,
            Http::get('http://127.0.0.1:' . $this->port . '/get-404')->status()
        );
    }

    public function test_can_get_500()
    {
        $this->startServer(
            $this->laravelPath . '/public/index.php',
        );
        $this->assertEquals(
            500,
            Http::get('http://127.0.0.1:' . $this->port . '/get-500')->status()
        );
    }
}
