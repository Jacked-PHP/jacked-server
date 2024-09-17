<?php

namespace JackedPhp\JackedServer\Services\Traits;

use Illuminate\Support\Arr;
use Monolog\Level;

trait Debuggable
{
    protected function report(
        string|iterable $message,
        array $context = [],
        Level $level = Level::Info,
        bool $skipPrint = false,
    ): void {
        if (in_array($level, [Level::Info, Level::Error, Level::Warning, Level::Critical])) {
            $this->logger->addRecord(level: $level, message: $message, context: $context);
        }

        if (!$this->debug) {
            return;
        }

        if ($level === Level::Debug) {
            $this->logger->addRecord(level: $level, message: $message, context: $context);
        }

        if (count($context) > 0) {
            $message = array_map(function ($msg) use ($context) {
                $replaced = str_replace(
                    array_map(fn($key) => '{' . $key . '}', array_keys($context)),
                    array_map(
                        fn($value) => is_array($value) ? json_encode($value) : $value,
                        array_values($context),
                    ),
                    $msg,
                );
                return preg_replace('/\{[^}]+}/', '', $replaced);
            }, Arr::wrap($message));
        }

        if ($skipPrint) {
            return;
        }

        $this->output->block(
            messages: $message,
            type: 'DEBUG',
            style: 'fg=white;bg=black',
            padding: true,
        );
    }
}