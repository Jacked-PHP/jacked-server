<?php

return [
    /**
     * @var string
     */
    'protocol' => env('CONVEYOR_PROTOCOL') ?? env('PUSHER_SCHEME', 'ws'),

    /**
     * @var string
     */
    'uri' => env('CONVEYOR_URI') ?? env('PUSHER_HOST', '127.0.0.1'),

    /**
     * @var int
     */
    'port' => env('CONVEYOR_PORT') ?? env('PUSHER_PORT', 8002),

    /**
     * @var string e.g.: key=value
     */
    'query' => env('CONVEYOR_QUERY', ''),
];
