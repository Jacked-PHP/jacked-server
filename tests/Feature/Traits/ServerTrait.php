<?php

namespace Tests\Feature\Traits;

use DirectoryIterator;
use Exception;
use JackedPhp\JackedServer\Commands\Traits\HasPersistence;
use JackedPhp\JackedServer\Helpers\Config;
use JackedPhp\LiteConnect\SQLiteFactory;
use OpenSwoole\Core\Coroutine\Pool\ClientPool;
use OpenSwoole\Coroutine;
use OpenSwoole\Coroutine\System;

trait ServerTrait
{
    use HasPersistence;

    protected ClientPool $connectionPool;

    protected function startServer(
        string $configFile = ROOT_DIR . '/config/jacked-server.php'
    ): int {
        $this->startDatabase(configFile: $configFile);

        $output = self::getServerProcesses();
        self::tearServerDown();
        if (!empty($output)) {
            throw new Exception('There is an active server in that port! Output: ' . $output);
        }

        $pid = null;
        $command = 'php ' . ROOT_DIR . '/server.php ' . $configFile . ' > /dev/null 2>&1 & echo $!';
        Coroutine::run(function () use ($configFile, $command, &$pid) {
            $pid = System::exec($command);
        });

        if (is_array($pid) && isset($pid['output'])) {
            $pid = trim($pid['output']);
        }

        if (!$pid || !is_numeric($pid)) {
            throw new Exception('Failed to start server or capture PID.');
        }

        $counter = 0;
        $threshold = 10; // seconds
        while (empty(self::getServerProcesses()) && $counter < $threshold) {
            $counter++;
            usleep(200000); // 0.2 seconds
        }

        return $pid;
    }

    public static function tearServerDown(): void
    {
        $processName = 'jacked-server-process';
        $command = "ps aux | grep '$processName' | grep -v grep | awk '{print $2}' | xargs -I {} kill -9 {}";
        Coroutine::run(fn() => System::exec($command));

        $output2 = self::getServerProcesses();
        if (!empty($output2)) {
            throw new Exception('Failed to kill server. Output: ' . $output2);
        }
    }

    public static function getServerProcesses(): string
    {
        $processName = 'jacked-server-process';
        $command = "ps aux | grep '$processName' | grep -v grep";
        $output = '';

        Coroutine::run(function () use (&$output, $command) {
            $output = System::exec($command);

            if (is_array($output) && isset($output['output'])) {
                $output = $output['output'];
            } elseif (is_array($output)) {
                $output = implode("\n", $output);
            }
        });

        return $output;
    }

    private function recursiveChmod($path, $dirPermission, $filePermission): void
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

    private function startDatabase(
        string $configFile = ROOT_DIR . '/config/jacked-server.php',
    ): void {
        $databaseConfig = Config::get(
            key: 'persistence.connections.' . Config::get('persistence.default'),
            configFile: $configFile,
        );
        $databaseFile = $databaseConfig['database'];

        if (file_exists($databaseFile)) {
            unlink($databaseFile);
            touch($databaseFile);
        }

        // create pool
        $this->connectionPool = new ClientPool(
            factory: SQLiteFactory::class,
            config: $databaseConfig,
            size: 1,
        );

        $this->applyMigration(pool: $this->connectionPool);
    }
}
