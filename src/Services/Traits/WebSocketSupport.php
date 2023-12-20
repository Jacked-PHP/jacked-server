<?php

namespace JackedPhp\JackedServer\Services\Traits;

use Exception;
use OpenSwoole\Http\Request;

trait WebSocketSupport
{
    /**
     * @param Request $request
     * @return array
     * @throws Exception
     */
    protected function processSecWebSocketKey(Request $request): array
    {
        $secWebSocketKey = $request->header['sec-websocket-key'];
        $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';

        if (
            0 === preg_match($patten, $secWebSocketKey)
            || 16 !== strlen(base64_decode($secWebSocketKey))
        ) {
            throw new Exception('Invalid Sec-WebSocket-Key');
        }

        $key = base64_encode(sha1($request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => $key,
            'Sec-WebSocket-Version' => '13',
        ];

        if(isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        return $headers;
    }
}
