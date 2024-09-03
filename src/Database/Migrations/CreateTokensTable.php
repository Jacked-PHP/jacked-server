<?php

namespace JackedPhp\JackedServer\Database\Migrations;

use JackedPhp\LiteConnect\Migration\Migration;
use PDO;

class CreateTokensTable implements Migration
{
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fd INTEGER NULL,
            token TEXT NOT NULL,
            allowed_channels TEXT NULL, -- comma separated list of allowed channels
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }

    public function down(PDO $pdo): void
    {
        // no way back
    }
}
