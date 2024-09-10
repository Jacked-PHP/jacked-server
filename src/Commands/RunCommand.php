<?php

namespace JackedPhp\JackedServer\Commands;

use Dotenv\Dotenv;
use JackedPhp\JackedServer\Commands\Traits\HasPersistence;
use JackedPhp\JackedServer\Data\ServerParams;
use JackedPhp\JackedServer\Data\ServerPersistence;
use JackedPhp\JackedServer\Helpers\Config;
use JackedPhp\JackedServer\Services\Server;
use JackedPhp\LiteConnect\SQLiteFactory;
use OpenSwoole\Core\Coroutine\Pool\ClientPool;
use OpenSwoole\Coroutine;
use OpenSwoole\Coroutine\System;
use OpenSwoole\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;

class RunCommand extends Command
{
    use HasPersistence;

    public const OPTION_CONFIG = 'config';

    private ?string $name = 'run';
    protected static $defaultName = 'run';

    protected static $defaultDescription = 'JackedPHP OpenSwoole Server';

    protected ServerParams $params;

    /**
     * @var array{
     *     host: string,
     *     port: string,
     *     inputFile: string,
     *     documentRoot: string,
     *     publicDocumentRoot: string,
     *     logPath: string,
     *     logLevel: string,
     *     io: SymfonyStyle,
     *     dispatcher: EventDispatcher
     * } $attributes
     */
    public array $attributes;

    protected function configure(): void
    {
        $this
            ->addOption(
                name: self::OPTION_CONFIG,
                mode: InputOption::VALUE_OPTIONAL,
                description: '(required) Configuration file. Default is ".env".',
                default: ROOT_DIR . '/.env',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dotenv = Dotenv::createImmutable(
            paths: dirname($input->getOption(self::OPTION_CONFIG)),
            names: basename($input->getOption(self::OPTION_CONFIG)),
        );
        $dotenv->load();

        $this->applyPidFile();
        $connectionPool = $this->initializeDatabase();

        $io = new SymfonyStyle($input, $output);

        $this->params = ServerParams::from([
            'host' => Config::get('host'),
            'port' => Config::get('port'),
            'inputFile' => Config::get('input-file'),
            'documentRoot' => Config::get('openswoole-server-settings.document_root'),
            'publicDocumentRoot' => Config::get('openswoole-server-settings.document_root'),
            'logPath' => Config::get('log.stream'),
            'logLevel' => Config::get('log.level'),
            'fastcgiHost' => Config::get('fastcgi.host'),
            'fastcgiPort' => Config::get('fastcgi.port'),
        ]);

        Server::init()
            ->host($this->params->host)
            ->port($this->params->port)
            ->inputFile($this->params->inputFile)
            ->documentRoot($this->params->documentRoot)
            ->publicDocumentRoot($this->params->publicDocumentRoot)
            ->output($io)
            ->eventDispatcher(new EventDispatcher())
            ->serverPersistence(new ServerPersistence(
                connectionPool: $connectionPool,
                conveyorPersistence: [],
            ))
            ->logPath($this->params->logPath)
            ->logLevel($this->params->logLevel)
            ->fastcgiHost($this->params->fastcgiHost)
            ->fastcgiPort($this->params->fastcgiPort)
            ->run();

        Coroutine::run(function () use ($io) {
            go(function () use ($io) {
                System::waitSignal(SIGINT, -1);
                $io->info('Server Terminated by User!');
                Process::kill(getmypid(), SIGKILL);
            });

            go(function () use ($io) {
                System::waitSignal(SIGKILL, -1);
                $io->info('Server Terminated by Signal SIGKILL!');
                Process::kill(getmypid(), SIGKILL);
            });
        });

        return Command::SUCCESS;
    }

    protected function initializeDatabase(): ClientPool
    {
        if (file_exists(Config::get('persistence.connections.' . Config::get('persistence.default') . '.database'))) {
            unlink(Config::get('persistence.connections.' . Config::get('persistence.default') . '.database'));
            touch(Config::get('persistence.connections.' . Config::get('persistence.default') . '.database'));
        }

        $connectionPool = new ClientPool(
            factory: SQLiteFactory::class,
            config: Config::get('persistence.connections.' . Config::get('persistence.default')),
            size: 1,
        );

        $this->applyMigration(pool: $connectionPool);

        return $connectionPool;
    }

    protected function applyPidFile(): void
    {
        $pidFile = ROOT_DIR . '/jacked-server.pid';
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
        file_put_contents($pidFile, getmypid());
    }
}
