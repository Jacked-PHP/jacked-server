<?php

namespace JackedPhp\JackedServer\Data;

use Bag\Bag;
use JackedPhp\JackedServer\Data\Traits\DataBagHelper;

readonly class RequestNotificationData extends Bag
{
    use DataBagHelper;

    public function __construct(
        public string $method,
        public string $uri,
        public array $headers,
        public string $body,
    ) {
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'method' => ['required', 'string'],
            'uri' => ['required', 'string'],
            'headers' => ['required', 'array'],
            'body' => ['sometimes', 'string'],
        ];
    }
}
