<?php

namespace JackedPhp\JackedServer\Events;

use Illuminate\Foundation\Events\Dispatchable;

class JackedRequestReceived
{
    use Dispatchable;

    public function __construct(
        public readonly array $requestOptions,
        public readonly string $content,
    ) {
    }
}
