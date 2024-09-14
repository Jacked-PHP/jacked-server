<?php

namespace JackedPhp\JackedServer\Commands;

use Dotenv\Dotenv;
use Exception;
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

    public const OPTION_DEBUG = 'debug';

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

    public bool $debug;

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
                description: 'Configuration file. Default is ".env".',
            )
            ->addOption(
                name: self::OPTION_DEBUG,
                mode: InputOption::VALUE_NONE,
                description: 'Displays debug information.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->debug = $input->getOption(self::OPTION_DEBUG);

        $this->debug('Server initializing...');

        if (!$this->setUserHomeDirectory()) {
            return Command::FAILURE;
        }

        if (!$this->verifyDependencies()) {
            return Command::FAILURE;
        }

        $optionConfig = $input->getOption(self::OPTION_CONFIG);
        $this->debug('Config set to: ' . $optionConfig);

        $inputPath = current($input->getArgument(self::ARGUMENT_PATH));
        $inputPath = empty($inputPath) ? null : $inputPath;
        $this->debug('Input path set to: ' . $inputPath);

        $this->loadEnv($optionConfig);

        $this->applyPidFile();

        $this->params = $this->prepareServerParams($inputPath);

        $this->debug('Server starting...');

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

    private function debug(string|iterable $message): void
    {
        if (!$this->debug) {
            return;
        }

        $this->io->block(
            messages: $message,
            type: 'DEBUG',
            style: 'fg=white;bg=black',
            padding: true,
        );
    }

    private function setUserHomeDirectory(): bool
    {
        $this->userHomeDirectory = getenv('HOME');
        if (!$this->userHomeDirectory) {
            $this->io->error("Unable to determine the home directory.");
            return false;
        }

        $this->debug('User home directory identified...');

        return true;
    }

    private function prepareServerParams(?string $inputPath): ServerParams|false
    {
        [
            'inputFile' => $inputFile,
            'documentRoot' => $documentRoot,
        ] = $this->processInputPath($inputPath);

        $data = [
            'host' => Config::get('host'),
            'port' => (int) Config::get('port'),
            'inputFile' => $inputFile,
            'documentRoot' => $documentRoot,
            'publicDocumentRoot' => $documentRoot,
            'logPath' => Config::get('log.stream'),
            'logLevel' => (int) Config::get('log.level'),
            'fastcgiHost' => Config::get('fastcgi.host'),
            'fastcgiPort' => (int) Config::get('fastcgi.port'),
        ];

        try {
            $this->debug([
                'Initializing Server params:',
                'Host: ' . $data['host'],
                'Port: ' . $data['port'],
                'Input File: ' . $data['inputFile'],
                'Document Root: ' . $data['documentRoot'],
                'Public Document Root: ' . $data['publicDocumentRoot'],
                'Log Path: ' . $data['logPath'],
                'Log Level: ' . $data['logLevel'],
                'FastCGI Host: ' . $data['fastcgiHost'],
                'FastCGI Port: ' . $data['fastcgiPort'],
            ]);
            $serverParams = ServerParams::from($data);
        } catch (Exception $e) {
            $this->io->error($e->getMessage());
            exit(Command::FAILURE);
        }

        return $serverParams;
    }

    private function verifyDependencies(): bool
    {
        if (!extension_loaded('openswoole')) {
            $this->io->error("OpenSwoole is available.");
            return false;
        }

        $this->debug('Dependencies verified...');

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

        $this->debug('Input file set to: ' . $inputFile);
        $this->debug('Document Root set to: ' . $documentRoot);

        return ['inputFile' => $inputFile, 'documentRoot' => $documentRoot];
    }

    private function loadEnv(?string $optionConfig = null): void
    {
        $baseDirectory = $optionConfig ? dirname($optionConfig) : ROOT_DIR;
        $envFile = $optionConfig ? basename($optionConfig) : '.env';

        if (!file_exists($baseDirectory . '/' . $envFile)) {
            $this->debug('Env config file doesn\'t exist: ' . $baseDirectory . '/' . $envFile);
            return;
        }

        $dotenv = Dotenv::createImmutable(
            paths: $baseDirectory,
            names: $envFile,
        );
        $dotenv->load();
        $this->debug('Env config loaded.');
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
        $this->debug('Server PID set: ' . $pidFile);
    }
}
