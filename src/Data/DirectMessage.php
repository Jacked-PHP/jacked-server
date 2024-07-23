<?php

namespace JackedPhp\JackedServer\Data;

use Bag\Bag;
use JackedPhp\JackedServer\Data\Traits\DataBagHelper;

readonly class DirectMessage extends Bag
{
    use DataBagHelper;

    public function __construct(
        public string $channel,
        public string $message,
    ) {
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'channel' => ['required', 'string'],
            'message' => ['required', 'string'],
        ];
    }
}
