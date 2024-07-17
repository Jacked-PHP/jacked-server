<?php

namespace JackedPhp\JackedServer\Events;

class JackedRequestError
{
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $error,
    ) {
    }
}
