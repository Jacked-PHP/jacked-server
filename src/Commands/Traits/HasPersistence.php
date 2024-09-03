<?php

namespace JackedPhp\JackedServer\Commands\Traits;

use JackedPhp\JackedServer\Database\Migrations\CreateTokensTable;
use JackedPhp\LiteConnect\Migration\MigrationManager;
use OpenSwoole\Core\Coroutine\Pool\ClientPool;
use OpenSwoole\Coroutine;

trait HasPersistence
{
    protected function applyMigration(ClientPool $pool): void
    {
        Coroutine::run(function () use ($pool, &$connection) {
            $connection = $pool->get();
        });

        $migrationManager = new MigrationManager($connection);
        $migrationManager->runMigrations([
            new CreateTokensTable(),
        ]);

        Coroutine::run(function () use ($pool, &$connection) {
            $pool->put($connection);
        });
    }
}
