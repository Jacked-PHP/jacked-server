<?php

use JackedPhp\JackedServer\Events\JackedRequestReceived;
use OpenSwoole\Server;
use JackedPhp\JackedServer\Events\JackedServerStarted;

return [
    // ------------------------------------------------------------
    // Running server details
    // ------------------------------------------------------------

    'host' => env('JACKED_SERVER_HOST', '0.0.0.0'),
    'port' => env('JACKED_SERVER_PORT', 8080),
    'server-type' => env('JACKED_SERVER_SERVER_TYPE', Server::POOL_MODE),
    'timeout' => env('JACKED_SERVER_TIMEOUT', 60),
    'readwrite-timeout' => env('JACKED_SERVER_READWRITE_TIMEOUT', 60),

    // ------------------------------------------------------------
    // SSL
    // ------------------------------------------------------------

    'ssl-port' => env('JACKED_SERVER_SSL_PORT', 443),
    'ssl-enabled' => env('JACKED_SERVER_SSL_ENABLED', false),
    'ssl-cert-file' => env('JACKED_SERVER_SSL_CERT_FILE'),
    'ssl-key-file' => env('JACKED_SERVER_SSL_KEY_FILE'),

    // ------------------------------------------------------------
    // Running server default options
    // ------------------------------------------------------------

    'server-protocol' => 'HTTP/1.1',
    'content-type' => 'text/html',
    'input-file' => env('JACKED_SERVER_INPUT_FILE', public_path('index.php')),
    'openswoole-server-settings' => [
        'document_root' => env('JACKED_SERVER_DOCUMENT_ROOT', public_path()),
        'enable_static_handler' => env('JACKED_SERVER_STATIC_ENABLED', true),
        'static_handler_locations' => explode(
            ',',
            env('JACKED_SERVER_STATIC_LOCATIONS', '/imgs,/css'),
        ),
    ],

    // ------------------------------------------------------------
    // Logging
    // ------------------------------------------------------------

    'log' => [
        'driver' => env('JACKED_SERVER_LOG_DRIVER', 'single'),
        'path' => storage_path(env('JACKED_SERVER_LOG_PATH', 'logs/jacked-server.log')),
        'replace-placeholders' => true,
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
        'host' => env('JACKED_SERVER_FASTCGI_HOST', '127.0.0.1'),
        'port' => env('JACKED_SERVER_FASTCGI_PORT', 9000),
    ],
];
