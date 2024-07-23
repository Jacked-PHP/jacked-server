<?php

namespace JackedPhp\JackedServer\Helpers;

use Illuminate\Support\Arr;

class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $configData = require(ROOT_DIR . "/config/jacked-server.php");

        // @phpstan-ignore-next-line
        return Arr::get($configData, $key, $default);
    }
}
