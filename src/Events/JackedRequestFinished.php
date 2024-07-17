<?php

namespace JackedPhp\JackedServer\Events;

class JackedRequestFinished
{
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }
}
