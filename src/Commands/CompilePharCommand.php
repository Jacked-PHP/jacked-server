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

            // Create the default stub from jackit.php entrypoint
            $defaultStub = $phar->createDefaultStub('jackit');

            // Add the rest of the apps files
            $phar->buildFromDirectory(ROOT_DIR); // @phpstan-ignore-line

            // Customize the stub to add the shebang
            $stub = "#!/usr/bin/env php \n" . $defaultStub;

            // Add the stub
            $phar->setStub($stub);

            $phar->stopBuffering();

            // plus - compressing it into gzip
            // $phar->compressFiles(Phar::GZ); // commented to avoid a known bug.

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
