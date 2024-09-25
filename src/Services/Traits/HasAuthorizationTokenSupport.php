<?php

namespace JackedPhp\JackedServer\Services\Traits;

use Firebase\JWT\JWT;
use Hook\Filter;
use JackedPhp\JackedServer\Constants as JackedServerConstants;
use JackedPhp\JackedServer\Database\Models\Token;
use JackedPhp\JackedServer\Events\JackedServerRequestIntercepted;
use JackedPhp\LiteConnect\Connection\Connection;

trait HasAuthorizationTokenSupport
{
    public function activateWsAuth(): void
    {
        Filter::addFilter(
            tag: JackedServerConstants::INTERCEPT_REQUEST,
            functionToAdd: function ($interceptedUris) {
                $interceptedUris[] = '/broadcasting/auth';
                return $interceptedUris;
            },
        );

        // This is a handler for the websocket auth request ONLY.
        $this->eventDispatcher->addListener(
            eventName: JackedServerRequestIntercepted::class,
            listener: function (JackedServerRequestIntercepted $event) {
                if (
                    !str_starts_with(
                        '/broadcasting/auth',
                        $event->request->server['request_uri'],
                    )
                    || $event->request->server['request_method'] !== 'POST'
                    || !is_string($this->websocketSecret)
                ) {
                    // not a websocket auth request
                    return;
                }

                // validate token in the header
                $bearerToken = $event->request->header['authorization'] ?? null;
                $bearerToken = explode(' ', $bearerToken)[1] ?? null;
                if ($bearerToken !== $this->websocketToken) {
                    $event->response->status(401);
                    $event->response->end();
                    return;
                }

                // validate necessary data: channel
                if (!isset($event->request->post['channel_name'])) {
                    $event->response->status(422);
                    $event->response->write(json_encode([
                        'error' => 'channel_name is required',
                    ]));
                    $event->response->end();
                    return;
                }

                $event->response->status(200);
                $event->response->write(json_encode([
                    'auth' => $this->generateToken(
                        $event->request->post['channel_name'],
                    )->token,
                ]));
                $event->response->end();
            },
        );
    }

    public function generateToken(string $channel): Token
    {
        $payload = [
            'channel' => $channel,
            'uniqid' => uniqid(),
        ];

        $token = JWT::encode($payload, $this->websocketSecret, 'HS256');

        /** @var Connection $connection */
        $connection = $this->serverPersistence->connectionPool->get();

        $tokenModel = new Token($connection);

        $token = $tokenModel->create([
            'token' => $token,
            'allowed_channels' => $channel,
        ]);

        $this->serverPersistence->connectionPool->put($connection);

        return $token;
    }

    public function hasToken(string $token): bool
    {
        /** @var Connection $connection */
        $connection = $this->serverPersistence->connectionPool->get();

        $tokenModel = new Token($connection);

        $data = $tokenModel->where('token', '=', $token)->get();

        $this->serverPersistence->connectionPool->put($connection);

        return count($data) > 0;
    }

    public function validateToken(string $token, string $channel): bool
    {
        /** @var Connection $connection */
        $connection = $this->serverPersistence->connectionPool->get();

        $tokenModel = new Token($connection);
        $tokenData = $tokenModel->where('token', '=', $token)->get();

        $this->serverPersistence->connectionPool->put($connection);

        if (!$tokenData) {
            return false;
        }

        if ($tokenData['allowed_channels'] === '') {
            return true;
        }

        $allowedChannels = explode(',', $tokenData['allowed_channels']);

        return in_array($channel, $allowedChannels);
    }
}
