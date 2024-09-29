<?php

namespace Tests\Feature\Traits;

use DirectoryIterator;
use Exception;
use JackedPhp\JackedServer\Commands\Traits\HasPersistence;
use JackedPhp\JackedServer\Data\ServerPersistence;
use JackedPhp\JackedServer\Helpers\Config;
use JackedPhp\JackedServer\Services\Server;
use JackedPhp\LiteConnect\SQLiteFactory;
use Monolog\Level;
use OpenSwoole\Core\Coroutine\Pool\ClientPool;
use OpenSwoole\Coroutine;
use OpenSwoole\Coroutine\System;
use OpenSwoole\Process as OpenSwooleProcess;
use Symfony\Component\EventDispatcher\EventDispatcher;

trait ServerTrait
{
    use HasPersistence;

    protected ClientPool $connectionPool;

    protected function startServer(
        string $configFile = ROOT_DIR . '/config/jacked-server.php'
    ): int {
        $this->startDatabase(configFile: $configFile);

        $output = self::getServerProcesses(configFile: $configFile);
        self::tearServerDown(configFile: $configFile);
        if (!empty($output)) {
            throw new Exception('There is an active server in that port! Output: ' . $output);
        }

        $httpServer = new OpenSwooleProcess(
            function (OpenSwooleProcess $worker) use ($configFile) {
                Server::init()
                    ->port(Config::get('port', configFile: $configFile))
                    ->inputFile(Config::get('input-file', configFile: $configFile))
                    ->documentRoot(Config::get('openswoole-server-settings.document_root', configFile: $configFile))
                    ->eventDispatcher(new EventDispatcher())
                    ->serverPersistence(new ServerPersistence(
                        connectionPool: $this->connectionPool,
                        conveyorPersistence: [],
                    ))
                    ->logPath(ROOT_DIR . '/logs/logs.log')
                    ->logLevel(Level::Warning->value)
                    ->websocketEnabled(Config::get('websocket.enabled', configFile: $configFile))
                    ->websocketAuth(Config::get('websocket.auth', configFile: $configFile))
                    ->websocketToken(Config::get('websocket.token', configFile: $configFile))
                    ->websocketSecret(Config::get('websocket.secret', configFile: $configFile))
                    ->run();
            }
        );

        $pid = $httpServer->start();

        $counter = 0;
        $threshold = 10; // seconds
        while (
            empty(self::getServerProcesses($configFile))
            && $counter < $threshold
        ) {
            $counter++;
            sleep(1);
        }

        return $pid;
    }

    public static function tearServerDown(string $configFile): void
    {
        $command = 'lsof -i -P -n '
            . '| grep LISTEN '
            . '| grep ' . Config::get('port', configFile: $configFile) . ' '
            . '| awk \'{print $2}\' '
            . '| xargs -I {} kill -9 {}';

        Coroutine::run(fn() => System::exec($command));

        // verify
        $output2 = self::getServerProcesses($configFile);
        if (!empty($output2)) {
            throw new Exception('Failed to kill server. Output: ' . $output2);
        }
    }

    public static function getServerProcesses(string $configFile): string
    {
        $output = '';
        $port = Config::get('port', configFile: $configFile);

        Coroutine::run(function () use (&$output, $port) {
            $output = System::exec('lsof -i -P -n | grep LISTEN | grep ' . $port);

            if (is_array($output)) {
                $output = $output['output'];
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
