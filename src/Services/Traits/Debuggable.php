<?php

namespace JackedPhp\JackedServer\Services\Traits;

use Illuminate\Support\Arr;
use Monolog\Level;
use Symfony\Component\Console\Style\OutputStyle;

trait Debuggable
{
    protected function report(
        string|iterable $message,
        array $context = [],
        Level $level = Level::Info,
        bool $skipPrint = false,
    ): void {
        $this->logger->addRecord(
            level: $level,
            message: is_array($message) ? json_encode($message) : $message,
            context: $context,
        );

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

        $isDebug = $level === Level::Debug;

        if ($skipPrint || (!$this->debug && $isDebug)) {
            return;
        }

        if (!$this->output instanceof OutputStyle) {
            echo ($isDebug ? '[DEBUG] ' : '') . $message . PHP_EOL;
            return;
        }

        $this->output->block(
            messages: $message,
            type: $isDebug ? 'DEBUG' : null,
            style: $this->getBlockStyle($level),
            padding: true,
        );
    }

    private function getBlockStyle(Level $level): ?string
    {
        return match ($level) {
            Level::Debug => 'fg=white;bg=black',
            Level::Info => 'fg=white;bg=blue',
            Level::Notice => 'fg=white;bg=black',
            Level::Warning => 'fg=black;bg=yellow',
            Level::Error => 'fg=red;bg=black',
            Level::Critical => 'fg=white;bg=red',
            Level::Alert => 'fg=white;bg=red',
            Level::Emergency => 'fg=white;bg=red',
            default => null,
        };
    }
}