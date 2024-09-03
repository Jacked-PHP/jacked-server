<?php

namespace JackedPhp\JackedServer\Commands;

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

    public const OPTION_HOST = 'host';
    public const OPTION_PORT = 'port';
    public const OPTION_INPUT_FILE = 'inputFile';
    public const OPTION_DOCUMENT_ROOT = 'documentRoot';
    public const OPTION_PUBLIC_DOCUMENT_ROOT = 'publicDocumentRoot';
    public const OPTION_LOG_PATH = 'logPath';
    public const OPTION_LOG_LEVEL = 'logLevel';

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
                name: self::OPTION_HOST,
                mode: InputOption::VALUE_REQUIRED,
                description: '(required) Server Host. Default in the config. e.g. --host=',
                default: Config::get('host'),
            )
            ->addOption(
                name: self::OPTION_PORT,
                mode: InputOption::VALUE_REQUIRED,
                description: '(required) Server Port. Default in the config. .e.g. --port=',
                default: Config::get('port'),
            )
            ->addOption(
                name: self::OPTION_INPUT_FILE,
                mode: InputOption::VALUE_REQUIRED,
                description: '(required) Input PHP file. Default: public/index.php. e.g. --inputFile=',
                default: Config::get('input-file'),
            )
            ->addOption(
                name: self::OPTION_DOCUMENT_ROOT,
                mode: InputOption::VALUE_REQUIRED,
                description: '(required) Input PHP file. Default: "." . e.g. --documentRoot=',
                default: Config::get('openswoole-server-settings.document_root'),
            )
            ->addOption(
                name: self::OPTION_PUBLIC_DOCUMENT_ROOT,
                mode: InputOption::VALUE_REQUIRED,
                description: '(required) Input PHP file. Default: public. e.g. --publicDocumentRoot=',
                default: Config::get('openswoole-server-settings.document_root'),
            )
            ->addOption(
                name: self::OPTION_LOG_PATH,
                mode: InputOption::VALUE_REQUIRED,
                description: '(required) Log file path. Default: logs/jacked-server.log. e.g. --logPath=',
                default: Config::get('log.stream'),
            )
            ->addOption(
                name: self::OPTION_LOG_LEVEL,
                mode: InputOption::VALUE_REQUIRED,
                description: '(required) Log level. Default: 300 (warning). e.g. --logLevel=',
                default: Config::get('log.level'),
            );
            // TODO: implement this option for websockets
            // ->addOption('wsPersistence', null, InputOption::VALUE_OPTIONAL, 'Ws Persistence.
            //   Default: conveyor', 'conveyor');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->applyPidFile();
        $connectionPool = $this->initializeDatabase();

        $io = new SymfonyStyle($input, $output);

        $this->params = ServerParams::from([
            'host' => $input->getOption(self::OPTION_HOST),
            'port' => $input->getOption(self::OPTION_PORT),
            'inputFile' => $input->getOption(self::OPTION_INPUT_FILE),
            'documentRoot' => $input->getOption(self::OPTION_DOCUMENT_ROOT),
            'publicDocumentRoot' => $input->getOption(self::OPTION_PUBLIC_DOCUMENT_ROOT),
            'logPath' => $input->getOption(self::OPTION_LOG_PATH),
            'logLevel' => $input->getOption(self::OPTION_LOG_LEVEL),
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
