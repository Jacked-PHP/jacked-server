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
    protected function configure()
    {
        $this->setName('compile')
            ->setDescription('Generate phar file for this server.')
            ->addArgument('output', InputArgument::REQUIRED, 'Output file (e.g.: convert.phar).');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $pharFile = $input->getArgument('output');

            // clean up
            if (file_exists($pharFile)) {
                unlink($pharFile);
            }

            if (file_exists($pharFile . '.gz')) {
                unlink($pharFile . '.gz');
            }

            // create phar
            $phar = new Phar($pharFile);

            // start buffering. Mandatory to modify stub to add shebang
            $phar->startBuffering();

            // Create the default stub from main.php entrypoint
            $defaultStub = $phar->createDefaultStub('jackit');

            // Add the rest of the apps files
            $phar->buildFromDirectory(ROOT_DIR);

            // Customize the stub to add the shebang
            $stub = "#!/usr/bin/env php \n" . $defaultStub;

            // Add the stub
            $phar->setStub($stub);

            $phar->stopBuffering();

            // plus - compressing it into gzip
            $phar->compressFiles(Phar::GZ);

            # Make the file executable
            chmod($pharFile, 0770);

            $output->writeln('<info>' . $pharFile . ' successfully created</info>');
        } catch (Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}