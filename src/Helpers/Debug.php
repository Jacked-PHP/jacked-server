<?php

namespace JackedPhp\JackedServer\Helpers;

class Debug
{
    public static function dumpIo(array $data): string
    {
        $routeValue = array_map(function($key, $value) {
            $value = is_array($value) ? self::dumpIo($value) : $value;
            return $key . ': "' . $value . '"';
        }, array_keys($data), $data);
        $routeValue = implode("\n", $routeValue);

        return $routeValue;
    }
}