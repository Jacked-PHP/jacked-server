<?php

namespace JackedPhp\JackedServer\Events;

use Illuminate\Foundation\Events\Dispatchable;

class JackedServerStarted
{
    use Dispatchable;

    public function __construct(
        public readonly string $host,
        public readonly int $port,
    ) {
    }
}
