<?php

namespace JackedPhp\JackedServer\Helpers;

class Debug
{
    public static function dumpIo(array $data): string
    {
        $routeValue = array_map(fn($key, $value) => $key . ': "' . $value . '"', array_keys($data), $data);
        $routeValue = implode("\n", $routeValue);

        return $routeValue;
    }
}