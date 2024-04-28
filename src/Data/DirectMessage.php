<?php

namespace JackedPhp\JackedServer\Data;

class DirectMessage
{
    public function __construct(
        public string $channel,
        public string $message,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            channel: $data['channel'],
            message: $data['message'],
        );
    }

    public function toArray(): array
    {
        return [
            'channel' => $this->channel,
            'message' => $this->message,
        ];
    }
}
