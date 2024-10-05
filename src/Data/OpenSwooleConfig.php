<?php

namespace JackedPhp\JackedServer\Data;

use Bag\Bag;
use Illuminate\Support\Str;
use JackedPhp\JackedServer\Data\Traits\DataBagHelper;

readonly class OpenSwooleConfig extends Bag
{
    use DataBagHelper;

    public function __construct(
        public string $documentRoot,
        public bool $enableStaticHandler,
        public int $reactorNum,
        public int $workerNum,
        public int $maxRequestExecutionTime,
        public ?string $sslCertFile,
        public ?string $sslKeyFile,
        public bool $openHttpProtocol,
        // public string $pidFile,
        public bool $httpCompression,
        public int $httpCompressionLevel,
        public bool $httpParseCookie = false,
        public array $staticHandlerLocations = [],
    ) {
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'documentRoot' => ['required', 'string'],
            'enableStaticHandler' => ['required', 'boolean'],
            'reactorNum' => ['required', 'integer'],
            'workerNum' => ['required', 'integer'],
            'maxRequestExecutionTime' => ['required', 'integer'],
            'sslCertFile' => ['nullable', 'string'],
            'sslKeyFile' => ['nullable', 'string'],
            'openHttpProtocol' => ['required', 'boolean'],
            // 'pidFile' => ['required', 'string'],
            'httpCompression' => ['required', 'boolean'],
            'httpCompressionLevel' => ['required', 'integer'],
            'httpParseCookie' => ['required', 'boolean'],
            'staticHandlerLocations' => ['array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnakeCasedData(): array
    {
        $data = $this->toArray();

        $snakeCasedData = [];
        foreach ($data as $key => $value) {
            $snakeCasedData[Str::snake($key)] = $value;
        }
        return $snakeCasedData;
    }
}
