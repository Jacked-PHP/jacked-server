<?php

use Monolog\Level;
use OpenSwoole\Util;

return [
    // ------------------------------------------------------------
    // Running server details
    // ------------------------------------------------------------

    'host' => $_ENV['JACKED_SERVER_HOST'] ?? '0.0.0.0',
    'port' => $_ENV['JACKED_SERVER_PORT'] ?? 8080,
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
    'input-file' => $_ENV['JACKED_SERVER_INPUT_FILE'] ?? ROOT_DIR . '/index.php',
    'openswoole-server-settings' => [
        'document_root' => $_ENV['JACKED_SERVER_DOCUMENT_ROOT'] ?? ROOT_DIR,
        'enable_static_handler' => $_ENV['JACKED_SERVER_STATIC_ENABLED'] ?? true,
        'static_handler_locations' => explode(
            ',',
            $_ENV['JACKED_SERVER_STATIC_LOCATIONS'] ?? '/imgs,/css,/js,/build',
        ),
    ],
    'conveyor-options' => [
        'websocket-auth-token' => $_ENV['JACKED_SERVER_WEBSOCKET_AUTH_TOKEN'] ?? null,
        'websocket-auth-url' => $_ENV['JACKED_SERVER_WEBSOCKET_AUTH_URL'] ?? null,
    ],

    // ------------------------------------------------------------
    // Logging
    // ------------------------------------------------------------

    'log' => [
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
        'enabled' => $_ENV['JACKED_SERVER_WEBSOCKET_ENABLED'] ?? false,
        'broadcaster' => false,
    ],

    // ------------------------------------------------------------
    // Proxy
    // ------------------------------------------------------------

    'proxy' => [
        'enabled' => $_ENV['JACKED_SERVER_PROXY_ENABLED'] ?? false,
        'host' => $_ENV['JACKED_SERVER_PROXY_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['JACKED_SERVER_PROXY_PORT'] ?? 3000,
        'allowed-headers' => [
            'content-type',
        ],
        'timeout' => $_ENV['JACKED_SERVER_PROXY_TIMEOUT'] ?? 5,
    ],
];
