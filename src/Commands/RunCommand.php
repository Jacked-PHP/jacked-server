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

    public const ARGUMENT_PATH = 'path';

    private ?string $name = 'run';
    protected static $defaultName = 'run';

    protected static $defaultDescription = 'JackedPHP OpenSwoole Server';

    protected ServerParams $params;

    protected SymfonyStyle $io;

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

    public string $userHomeDirectory;

    protected function configure(): void
    {
        $this
            ->addArgument(
                name: self::ARGUMENT_PATH,
                mode: InputOption::VALUE_OPTIONAL,
                description: 'Path to the configuration file.',
            )
            ->addOption(
                name: self::OPTION_CONFIG,
                mode: InputOption::VALUE_OPTIONAL,
                description: '(required) Configuration file. Default is ".env".',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->userHomeDirectory = getenv('HOME');
        if (!$this->userHomeDirectory) {
            $this->io->error("Unable to determine the home directory.");
            return Command::FAILURE;
        }

        if (!$this->verifyDependencies()) {
            return Command::FAILURE;
        }

        $optionConfig = $input->getOption(self::OPTION_CONFIG);
        $inputPath = current($input->getArgument(self::ARGUMENT_PATH));
        $inputPath = empty($inputPath) ? null : $inputPath;

        $this->loadEnv($optionConfig);
        $this->applyPidFile();

        [
            'inputFile' => $inputFile,
            'documentRoot' => $documentRoot,
        ] = $this->processInputPath($inputPath);

        $this->params = ServerParams::from([
            'host' => Config::get('host'),
            'port' => Config::get('port'),
            'inputFile' => $inputFile,
            'documentRoot' => $documentRoot,
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
            ->output($this->io)
            ->eventDispatcher(new EventDispatcher())
            ->serverPersistence(new ServerPersistence(
                connectionPool: $this->initializeDatabase(),
                conveyorPersistence: [],
            ))
            ->logPath($this->params->logPath)
            ->logLevel($this->params->logLevel)
            ->fastcgiHost($this->params->fastcgiHost)
            ->fastcgiPort($this->params->fastcgiPort)
            ->run();

        return Command::SUCCESS;
    }

    private function verifyDependencies(): bool
    {
        if (!extension_loaded('openswoole')) {
            $this->io->error("OpenSwoole is available.");
            return false;
        }

        return true;
    }

    /**
     * @return array{inputFile: string, documentRoot: string}
     */
    private function processInputPath(?string $inputPath = null): array
    {
        $documentRoot = null;
        $inputFile = null;

        if ($inputPath !== null) {
            $documentRoot = is_dir($inputPath) ? $inputPath : dirname($inputPath);
            $inputFile = is_dir($inputPath) ? $inputPath . '/index.php' : $inputPath;
        }

        $inputFile = $inputFile
            ?? Config::get('input-file')
            ?? getcwd() . '/index.php';

        $documentRoot = $documentRoot
            ?? Config::get('openswoole-server-settings.document_root')
            ?? getcwd();

        return ['inputFile' => $inputFile, 'documentRoot' => $documentRoot];
    }

    private function loadEnv(?string $optionConfig = null): void
    {
        $baseDirectory = $optionConfig ? dirname($optionConfig) : ROOT_DIR;
        $envFile = $optionConfig ? basename($optionConfig) : '.env';

        if (!file_exists($baseDirectory . '/' . $envFile)) {
            return;
        }

        $dotenv = Dotenv::createImmutable(
            paths: $baseDirectory,
            names: $envFile,
        );
        $dotenv->load();
    }

    private function initializeDatabase(): ClientPool
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

    private function applyPidFile(): void
    {
        $pidFile = $this->userHomeDirectory . '/jacked-server.pid';
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
        file_put_contents($pidFile, getmypid());
    }
}
