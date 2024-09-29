<?php

namespace JackedPhp\JackedServer\Helpers;

class Debug
{
    public static function dumpIo(array $data, int $level = 0): string
    {
        $routeValue = array_map(function($key, $value) use ($level) {
            if (is_array($value)) {
                $valueDump = self::dumpIo($value, level: $level + 1);
                return PHP_EOL . (str_repeat(' ', $level * 3)) . $key . ":\n" . $valueDump;
            }
            return (str_repeat(' ', $level * 3)) . $key . ': "' . $value . '"';
        }, array_keys($data), $data);
        $routeValue = implode("\n", $routeValue);

        return $routeValue;
    }
}