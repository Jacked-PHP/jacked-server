<?php

namespace JackedPhp\JackedServer\Events;

class JackedRequestReceived
{
    public function __construct(
        public readonly array $requestOptions,
        public readonly string $content,
    ) {
    }
}
