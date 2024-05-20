<?php

namespace JackedPhp\JackedServer\Events;

use Illuminate\Foundation\Events\Dispatchable;

class JackedRequestError
{
    use Dispatchable;

    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $error,
    ) {
    }
}
