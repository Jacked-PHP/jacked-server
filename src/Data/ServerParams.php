<?php

namespace JackedPhp\JackedServer\Data;

use Bag\Bag;
use JackedPhp\JackedServer\Data\Traits\DataBagHelper;

readonly class ServerParams extends Bag
{
    use DataBagHelper;

    public function __construct(
        public string $host,
        public int $port,
        public string $inputFile,
        public string $documentRoot,
        public string $publicDocumentRoot,
        public string $logPath,
        public int $logLevel,
        public string $fastcgiHost,
        public int $fastcgiPort,
    ) {
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'host' => ['required', 'string'],
            'port' => ['required', 'integer'],
            'inputFile' => ['required', 'string'],
            'documentRoot' => ['required', 'string'],
            'publicDocumentRoot' => ['required', 'string'],
            'logPath' => ['required', 'string'],
            'logLevel' => ['required', 'integer'],
            'fastcgiHost' => ['required', 'string'],
            'fastcgiPort' => ['required', 'integer'],
        ];
    }
}
