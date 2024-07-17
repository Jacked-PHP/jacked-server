<?php

namespace JackedPhp\JackedServer\Helpers;

use Conveyor\Helpers\Arr;

class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return Arr::get(require(ROOT_DIR . "/config/jacked-server.php"), $key, $default);
    }
}