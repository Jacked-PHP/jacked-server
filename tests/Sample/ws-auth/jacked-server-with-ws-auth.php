<?php

use Illuminate\Support\Arr;
use Monolog\Level;
use OpenSwoole\Util;

return [
    // ------------------------------------------------------------
    // Running server details
    // ------------------------------------------------------------

    'host' => $_ENV['JACKED_SERVER_HOST'] ?? '0.0.0.0',
    'port' => 8081,
    'server-type' => $_ENV['JACKED_SERVER_SERVER_TYPE'] ?? 2,
    'timeout' => $_ENV['JACKED_SERVER_TIMEOUT'] ?? 60,
    'readwrite-timeout' => $_ENV['JACKED_SERVER_READWRITE_TIMEOUT'] ?? 60,

    // ------------------------------------------------------------
    // SSL
    // ------------------------------------------------------------

    'ssl-port' => $_ENV['JACKED_SERVER_SSL_PORT'] ?? 443,
    'ssl-enabled' => $_ENV['JACKED_SERVER_SSL_ENABLED'] ?? false,
    'ssl-cert-file' => $_ENV['JACKED_SERVER_SSL_CERT_FILE'] ?? null,
    'ssl-key-file' => $_ENV['JACKED_SERVER_SSL_KEY_FILE'] ?? null,

    // ------------------------------------------------------------
    // Running server default options
    // ------------------------------------------------------------

    'server-protocol' => 'HTTP/1.1',
    'content-type' => 'text/html',
    'reactor-num' => $_ENV['JACKED_SERVER_REACTOR_NUM'] ?? Util::getCPUNum() + 2,
    'worker-num' => $_ENV['JACKED_SERVER_WORKER_NUM'] ?? Util::getCPUNum() + 2,
    // @phpstan-ignore-next-line
    'input-file' => $_ENV['JACKED_SERVER_INPUT_FILE'] ?? ROOT_DIR . '/index.php',
    'openswoole-server-settings' => [
        // @phpstan-ignore-next-line
        'document_root' => $_ENV['JACKED_SERVER_DOCUMENT_ROOT'] ?? ROOT_DIR,
        'enable_static_handler' => $_ENV['JACKED_SERVER_STATIC_ENABLED'] ?? true,
        'static_handler_locations' => explode(
            ',',
            $_ENV['JACKED_SERVER_STATIC_LOCATIONS'] ?? '/imgs,/css,/js,/build',
        ),

        // reactor and workers
        'reactor_num' => 4,
        'worker_num' => 4,

        // timeout
        'max_request_execution_time' => 60,

        // ssl
        'ssl_cert_file' => null,
        'ssl_key_file' => null,
        'open_http_protocol' => false,

        // @phpstan-ignore-next-line
        // 'pid_file' => $_ENV['JACKED_SERVER_PID_FILE'] ?? ROOT_DIR . '/jacked-server.pid',
    ],
    'conveyor-options' => [
        'websocket-auth-token' => $_ENV['JACKED_SERVER_WEBSOCKET_AUTH_TOKEN'] ?? null,
        'websocket-auth-url' => $_ENV['JACKED_SERVER_WEBSOCKET_AUTH_URL'] ?? null,
    ],

    // ------------------------------------------------------------
    // Audit
    // ------------------------------------------------------------

    'audit' => [
        'enabled' => true,
    ],

    // ------------------------------------------------------------
    // Logging
    // ------------------------------------------------------------

    'log' => [
        // @phpstan-ignore-next-line
        'stream' => $_ENV['JACKED_SERVER_LOG_PATH'] ?? ROOT_DIR . '/logs/jacked-server.log',
        'level' => $_ENV['JACKED_SERVER_LOG_LEVEL'] ?? Level::Warning,
    ],

    // ------------------------------------------------------------
    // FastCgi Client Info
    //
    // If you are using a unix socket, you can use the following
    // host: unix:///path/to/php/socket
    // port: -1
    //
    // If you are using a tcp socket, you can use the following
    // host: 127.0.0.1
    // port: 9000
    // ------------------------------------------------------------

    'fastcgi' => [
        'host' => $_ENV['JACKED_SERVER_FASTCGI_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['JACKED_SERVER_FASTCGI_PORT'] ?? 9000,
    ],

    // ------------------------------------------------------------
    // WebSockets
    // ------------------------------------------------------------

    'websocket' => [
        'enabled' => true,
        'secret' => 'secret',
        'token' => '12345',
        'auth' => true,
        'acknowledgment' => false,
    ],

    // ------------------------------------------------------------
    // Request Interceptor
    // ------------------------------------------------------------

    'request-interceptor' => [
        // These are URIs that will be intercepted by the Jacked Server, and dispatch the event
        // JackedPhp\JackedServer\Events\RequestInterceptedEvent::class with the request and
        // response objects. e.g. /api/v1/intercepted,/api/v1/intercepted2
        'uris' => explode(',', $_ENV['JACKED_SERVER_REQUEST_INTERCEPTED_URIS'] ?? ''),
    ],

    // ------------------------------------------------------------
    // Persistence
    // ------------------------------------------------------------

    'persistence' => [
        'default' => 'sqlite',
        'connections' => [
            'sqlite' => [
                'database' => ROOT_DIR . '/ws-auth/database.sqlite',
            ],
        ],
    ]
];
