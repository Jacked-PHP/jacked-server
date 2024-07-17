<?php

namespace JackedPhp\JackedServer\Events;

class JackedServerStarted
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
    ) {
    }
}
