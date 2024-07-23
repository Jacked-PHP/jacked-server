<?php

namespace Tests\Feature\Traits;

use DirectoryIterator;
use Exception;
use Illuminate\Support\Facades\Process;
use JackedPhp\JackedServer\Helpers\Config;
use JackedPhp\JackedServer\Services\Server;
use OpenSwoole\Coroutine;
use OpenSwoole\Coroutine\System;
use OpenSwoole\Process as OpenSwooleProcess;
use Symfony\Component\EventDispatcher\EventDispatcher;

trait ServerTrait
{
    protected function startServer(): int
    {
        $output = self::getServerProcesses();
        if (!empty($output)) {
            throw new Exception('There is an active server in that port! Output: ' . $output);
        }

        $httpServer = new OpenSwooleProcess(
            function (OpenSwooleProcess $worker) {
                (new Server(
                    host: '0.0.0.0',
                    port: Config::get('port'),
                ))->run();
            }
        );

        $pid = $httpServer->start();

        $counter = 0;
        $threshold = 10; // seconds
        while (
            empty(self::getServerProcesses())
            && $counter < $threshold
        ) {
            $counter++;
            sleep(1);
        }

        return $pid;
    }

    public static function tearServerDown(): void
    {
        Coroutine::run(fn() => System::exec(
            'lsof -i -P -n '
            . '| grep LISTEN '
            . '| grep ' . Config::get('port') . ' '
            . '| awk \'{print $2}\' '
            . '| xargs -I {} kill -9 {}'
        ));

        // verify
        $output2 = self::getServerProcesses();
        if (!empty($output2)) {
            throw new Exception('Failed to kill server. Output: ' . $output2);
        }
    }

    public static function getServerProcesses(): string
    {
        $output = '';

        Coroutine::run(function () use (&$output) {
            $output = System::exec('lsof -i -P -n | grep LISTEN | grep ' . Config::get('port'));

            if (is_array($output)) {
                $output = $output['output'];
            }
        });

        return $output;
    }

    private function recursiveChmod($path, $dirPermission, $filePermission)
    {
        if (is_dir($path)) {
            chmod($path, $dirPermission);
            $dir = new DirectoryIterator($path);
            foreach ($dir as $item) {
                if ($item->isDot()) {
                    continue;
                }
                $this->recursiveChmod($item->getPathname(), $dirPermission, $filePermission);
            }
        } else {
            chmod($path, $filePermission);
        }
    }
}
