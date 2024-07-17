<?php

namespace JackedPhp\JackedServer\Commands;

use Exception;
use JackedPhp\JackedServer\Helpers\Config;
use JackedPhp\JackedServer\Services\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class RunCommand extends Command
{
    const OPTION_HOST = 'host';
    const OPTION_PORT = 'port';
    const OPTION_INPUT_FILE = 'inputFile';
    const OPTION_DOCUMENT_ROOT = 'documentRoot';
    const OPTION_PUBLIC_DOCUMENT_ROOT = 'publicDocumentRoot';
    const OPTION_LOG_PATH = 'logPath';
    const OPTION_LOG_LEVEL = 'logLevel';
    const OPTION_SILENCE = 'silence';

    protected static $defaultName = 'run';

    protected static $defaultDescription = 'JackedPHP OpenSwoole Server';

    protected function configure(): void
    {
        $this
            ->addOption(
                name: self::OPTION_HOST,
                mode: InputOption::VALUE_REQUIRED,
                description: '(required) Server Host. Default in the config. e.g. --host=',
            )
            ->addOption(
                name: self::OPTION_PORT,
                mode: InputOption::VALUE_REQUIRED,
                description: '(required) Server Port. Default in the config. .e.g. --port=',
            )
            ->addOption(
                name: self::OPTION_INPUT_FILE,
                mode: InputOption::VALUE_REQUIRED,
                description: '(required) Input PHP file. Default: public/index.php. e.g. --inputFile=',
            )
            ->addOption(
                name: self::OPTION_DOCUMENT_ROOT,
                mode: InputOption::VALUE_REQUIRED,
                description: '(required) Input PHP file. Default: "." . e.g. --documentRoot=',
            )
            ->addOption(
                name: self::OPTION_PUBLIC_DOCUMENT_ROOT,
                mode: InputOption::VALUE_REQUIRED,
                description: '(required) Input PHP file. Default: public. e.g. --publicDocumentRoot=',
            )
            ->addOption(
                name: self::OPTION_LOG_PATH,
                mode: InputOption::VALUE_REQUIRED,
                description: '(required) Log file path. Default: logs/jacked-server.log. e.g. --logPath=',
            )
            ->addOption(
                name: self::OPTION_LOG_LEVEL,
                mode: InputOption::VALUE_REQUIRED,
                description: '(required) Log level. Default: 300 (warning). e.g. --logLevel=',
            )
            ->addOption(
                name: self::OPTION_SILENCE,
                mode: InputOption::VALUE_OPTIONAL,
                description: 'Defines if Jacked Server will prompt if any required option is not present. Default: false. e.g. --silence=true',
                default: false,
            );
            // TODO: implement this option for websockets
            // ->addOption('wsPersistence', null, InputOption::VALUE_OPTIONAL, 'Ws Persistence. Default: conveyor', 'conveyor');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $silence = $input->getOption(self::OPTION_SILENCE);
        $logPath = $input->getOption(self::OPTION_LOG_PATH);
        $logLevel = $input->getOption(self::OPTION_LOG_LEVEL);
        try {
            foreach ([
                self::OPTION_HOST,
                self::OPTION_PORT,
                self::OPTION_INPUT_FILE,
                self::OPTION_DOCUMENT_ROOT,
                self::OPTION_PUBLIC_DOCUMENT_ROOT,
            ] as $option) {
                ${$option} = $input->getOption($option);
                $this->validate(option: $option, param: ${$option}, silence: $silence, io: $io);
            }
        } catch (Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $server = new Server(
            host: $host,
            port: $port,
            inputFile: $inputFile,
            documentRoot: $documentRoot,
            publicDocumentRoot: $publicDocumentRoot,
            output: $io,
            logPath: $logPath,
            logLevel: $logLevel,
            // TODO: make this configurable
            // wsPersistence: $input->getOption('wsPersistence'),
        );

        $server->run();

        return Command::SUCCESS;
    }

    private function validate(
        string $option,
        mixed &$param,
        bool $silence,
        SymfonyStyle $io
    ): void {
        $errorMessages = [
            self::OPTION_HOST => 'Host is required (--host=)',
            self::OPTION_PORT => 'Port is required (--port=)',
            self::OPTION_INPUT_FILE => 'Input File is required (--inputFile=)',
            self::OPTION_DOCUMENT_ROOT => 'Document Root is required (--documentRoot=)',
            self::OPTION_PUBLIC_DOCUMENT_ROOT => 'Public Document Root is required (--publicDocumentRoot=)',
        ];

        $defaults = [
            self::OPTION_HOST => Config::get('host'),
            self::OPTION_PORT => Config::get('port'),
            self::OPTION_INPUT_FILE => Config::get('input-file'),
            self::OPTION_DOCUMENT_ROOT => Config::get('openswoole-server-settings.document_root'),
            self::OPTION_PUBLIC_DOCUMENT_ROOT => Config::get('openswoole-server-settings.document_root'),
        ];

        if (null === $param && !$silence) {
            $question = new Question('What is the ' . $option . ' for your server?', $defaults[$option]);
            $param = $io->askQuestion($question);
        } elseif (null === $param) {
            throw new Exception($errorMessages[$option]);
        }
    }
}
