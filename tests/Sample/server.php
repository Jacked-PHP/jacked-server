<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use JackedPhp\JackedServer\Data\ServerPersistence;
use JackedPhp\JackedServer\Helpers\Config;
use JackedPhp\JackedServer\Services\Server;
use JackedPhp\LiteConnect\SQLiteFactory;
use Monolog\Level;
use OpenSwoole\Core\Coroutine\Pool\ClientPool;
use OpenSwoole\Util;
use Symfony\Component\EventDispatcher\EventDispatcher;

const ROOT_DIR = __DIR__;
const CONFIG_FILE = ROOT_DIR . '/config/jacked-server.php';
const MONITOR_CHANNEL = 'jacked-monitor';
const IS_PHAR = false;

$configFile = $argv[1] ?? ROOT_DIR . '/config/jacked-server.php';

Util::setProcessName('jacked-server-process');

$databaseConfig = Config::get(
    key: 'persistence.connections.' . Config::get('persistence.default'),
    configFile: $configFile,
);

$connectionPool = new ClientPool(
    factory: SQLiteFactory::class,
    config: $databaseConfig,
    size: 1,
);

Server::init()
    ->port(Config::get('port', configFile: $configFile))
    ->inputFile(Config::get('input-file', configFile: $configFile))
    ->documentRoot(Config::get('openswoole-server-settings.document_root', configFile: $configFile))
    ->eventDispatcher(new EventDispatcher())
    ->serverPersistence(new ServerPersistence(
        connectionPool: $connectionPool,
        conveyorPersistence: [],
    ))
    ->logPath(ROOT_DIR . '/logs/logs.log')
    ->logLevel(Level::Warning->value)
    ->websocketEnabled(Config::get('websocket.enabled', configFile: $configFile))
    ->websocketAuth(Config::get('websocket.auth', configFile: $configFile))
    ->websocketToken(Config::get('websocket.token', configFile: $configFile))
    ->websocketSecret(Config::get('websocket.secret', configFile: $configFile))
    ->run();
