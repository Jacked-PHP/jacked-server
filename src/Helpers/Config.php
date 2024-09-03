<?php

namespace JackedPhp\JackedServer\Helpers;

use Illuminate\Support\Arr;

class Config
{
    public static function get(
        string $key,
        mixed $default = null,
        string $configFile = ROOT_DIR . CONFIG_FILE,
    ): mixed {
        $configData = require($configFile);

        // @phpstan-ignore-next-line
        return Arr::get($configData, $key, $default);
    }
}
