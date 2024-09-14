<?php

namespace JackedPhp\JackedServer\Commands;

use Exception;
use Phar;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// reference: https://blog.programster.org/creating-phar-files

class CompilePharCommand extends Command
{
    private ?string $name = 'compile-phar';

    protected static $defaultName = 'compile-phar';

    protected static $defaultDescription = 'Compile phar file for this server.';

    protected function configure()
    {
        $this->addArgument('output', InputArgument::REQUIRED, 'Output file (e.g.: convert.phar).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $pharFile = $input->getArgument('output');
            // $excludeDirs = ['tests', 'logs', 'docs'];

            if (file_exists($pharFile)) {
                unlink($pharFile);
            }

            if (file_exists($pharFile . '.gz')) {
                unlink($pharFile . '.gz');
            }

            $phar = new Phar($pharFile);
            $phar->startBuffering();
            $phar->buildFromDirectory(ROOT_DIR); // @phpstan-ignore-line
            $defaultStub = $phar->createDefaultStub('jackit');
            $stub = "#!/usr/bin/env php \n" . $defaultStub;
            $phar->setStub($stub);
            $phar->stopBuffering();
            // $phar->compressFiles(Phar::GZ); // commented to avoid a known bug.

            chmod($pharFile, 0770);

            $output->writeln('<info>' . $pharFile . ' successfully created</info>');
        } catch (Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
