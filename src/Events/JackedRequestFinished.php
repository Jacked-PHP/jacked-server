<?php

namespace JackedPhp\JackedServer\Events;

use Illuminate\Foundation\Events\Dispatchable;

class JackedRequestFinished
{
    use Dispatchable;

    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }
}
