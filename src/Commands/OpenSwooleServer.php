<?php

namespace JackedPhp\JackedServer\Commands;

use Illuminate\Console\Command;
use JackedPhp\JackedServer\Services\Server;

class OpenSwooleServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jacked:server
                            {--host= : Server Host. Default in the config.}
                            {--port= : Server Port. Default in the config.}
                            {--inputFile= : Input PHP file. Default: public/index.php}
                            {--documentRoot= : Input PHP file. Default: public}
                            {--publicDocumentRoot= : Input PHP file. Default: public}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'JackedPHP OpenSwoole Server';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        (new Server(
            host: $this->option('host'),
            port: $this->option('port'),
            inputFile: $this->option('inputFile'),
            documentRoot: $this->option('documentRoot'),
            publicDocumentRoot: $this->option('publicDocumentRoot'),
            output: $this->getOutput(),
            wsPersistence: config('jacked-server.ws-persistence', []),
        ))->run();
    }
}
