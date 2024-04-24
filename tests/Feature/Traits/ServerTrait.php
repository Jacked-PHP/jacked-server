<?php

namespace Tests\Feature\Traits;

use DirectoryIterator;
use Exception;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use JackedPhp\JackedServer\Services\Server;
use Mustachio\Service as Stache;
use OpenSwoole\Process as OpenSwooleProcess;
use function Orchestra\Testbench\artisan;

trait ServerTrait
{
    public string $laravelPath;
    public int $port = 8989;

    protected ?Manager $manager = null;

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        $databaseCredentials = [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 33061,
            'database' => 'laravel',
            'username' => 'root',
            'password' => 'password',
        ];

        $app['config']->set(
            'database.connections.mysql',
            $databaseCredentials,
        );

        $app['config']->set(
            'jacked-server.input-file',
            $inputFile ?? str_replace('/Feature/Traits', '', __DIR__) . '/Assets/laravel/public/index.php',
        );

        $app['config']->set(
            'jacked-server.openswoole-server-settings.document_root',
            $documentRoot ?? str_replace('/Feature/Traits', '', __DIR__) . '/Assets/laravel/public',
        );

        $app['config']->set(
            'jacked-server.openswoole-server-settings.static_handler_locations',
            ['/css', '/js', '/img'],
        );
    }

    protected function getManager(array $databaseOptions): Manager
    {
        if (null !== $this->manager) {
            return $this->manager;
        }

        $this->manager = new Manager;
        $this->manager->addConnection($databaseOptions, 'socket-conveyor');

        return $this->manager;
    }

    protected function startServer(
        ?string $inputFile = null,
        ?string $documentRoot = null,
        bool $websocketEnabled = false,
        bool $broadcasterEnabled = false,
    ): int {
        if (null !== $inputFile) {
            Config::set(
                'jacked-server.input-file',
                $inputFile ?? str_replace('/Feature/Traits', '', __DIR__) . '/Assets/laravel/public/index.php',
            );
        }

        if (null !== $documentRoot) {
            Config::set(
                'jacked-server.openswoole-server-settings.document_root',
                $documentRoot ?? str_replace('/Feature/Traits', '', __DIR__) . '/Assets/laravel/public',
            );
        }

        if ($websocketEnabled) {
            Config::set('jacked-server.websocket.enabled', true);
        }

        if ($broadcasterEnabled) {
            Config::set('jacked-server.websocket.broadcaster', true);
        }

        $output = $this->get_server_processes();
        if (!empty($output)) {
            throw new Exception('There is an active server in that port! Output: ' . $output);
        }

        $httpServer = new OpenSwooleProcess(
            function(OpenSwooleProcess $worker) {
                (new Server(
                    port: $this->port,
                ))->run();
            }
        );

        $pid = $httpServer->start();

        $counter = 0;
        $threshold = 10; // seconds
        while(
            empty($this->get_server_processes())
            && $counter < $threshold
        ) {
            $counter++;
            sleep(1);
        }

        return $pid;
    }

    /**
     * @after
     */
    public function tear_server_down()
    {
        // kill processes
        Process::run('lsof -i -P -n | grep LISTEN | grep ' . $this->port . ' | awk \'{print $2}\' | xargs -I {} kill -9 {}');

        // verify
        $output2 = $this->get_server_processes();
        if (!empty($output2)) {
            throw new Exception('Failed to kill server. Output: ' . $output2);
        }

        if (null === $this->manager) {
            return;
        }

        $this->manager->getConnection('socket-conveyor')->disconnect();
    }

    public function get_server_processes()
    {
        return Process::run('lsof -i -P -n | grep LISTEN | grep ' . $this->port)->output();
    }

    protected function getPackageProviders($app)
    {
        return [
            'JackedPhp\JackedServer\JackedServerProvider',
        ];
    }

    protected function setUpLaravel()
    {
        $assetsPath = __DIR__ . '/../../Assets';

        $this->laravelPath = $assetsPath . '/laravel';
        if (!file_exists($this->laravelPath)) {
            Process::run(['composer', 'create-project', 'laravel/laravel', $this->laravelPath]);
            $this->recursiveChmod($this->laravelPath, 0777, 0777);

            // install sanctum
            Process::path($this->laravelPath)
                ->run(['composer', 'require', 'laravel/sanctum']);
            Process::path($this->laravelPath)
                ->run(['php', 'artisan', 'vendor:publish', '--provider="Laravel\Sanctum\SanctumServiceProvider"']);

            // install laravel-conveyor
            Process::path($this->laravelPath)
                ->run(['composer', 'require', 'kanata-php/conveyor-laravel-broadcaster']);
            Process::path($this->laravelPath)
                ->run(['php', 'artisan', 'vendor:publish', '--provider="Kanata\LaravelBroadcaster\ConveyorServiceProvider"']);
        }

        // web route
        $newWebRouteFile = $assetsPath . '/web-sample.php';
        $webRouteFile = $this->laravelPath . '/routes/web.php';
        copy($newWebRouteFile, $webRouteFile);

        // api route
        $newApiRouteFile = $assetsPath . '/api-sample.php';
        $apiRouteFile = $this->laravelPath . '/routes/api.php';
        copy($newApiRouteFile, $apiRouteFile);

        // app config
        $newAppConfigFile = $assetsPath . '/app-config.php';
        $appConfigFile = $this->laravelPath . '/config/app.php';
        copy($newAppConfigFile, $appConfigFile);

        // public index
        $newPublicIndexFile = $assetsPath . '/public-index-sample.php';
        $publicIndexFile = $this->laravelPath . '/public/index.php';
        copy($newPublicIndexFile, $publicIndexFile);

        // .env
        $newEnvFile = $assetsPath . '/env-sample';
        $parsedContent = Stache::parse(file_get_contents($newEnvFile), ['DB_DATABASE_PLACEHOLDER' => __DIR__ . '/../../Assets/database/database.sqlite']);
        $envFile = $this->laravelPath . '/.env';
        unlink($envFile);
        file_put_contents($envFile, $parsedContent);

        // broadcasting config
        $newBroadcastingConfigFile = $assetsPath . '/broadcasting-sample.php';
        $broadcastingConfigFile = $this->laravelPath . '/config/broadcasting.php';
        copy($newBroadcastingConfigFile, $broadcastingConfigFile);

        // broadcast service provider
        $newBroadcastProviderFile = $assetsPath . '/BroadcastServiceProvider-sample.php';
        $broadcastProviderFile = $this->laravelPath . '/app/Providers/BroadcastServiceProvider.php';
        copy($newBroadcastProviderFile, $broadcastProviderFile);

        // http kernel
        $newHttpKernelFile = $assetsPath . '/Kernel-sample.php';
        $httpKernelFile = $this->laravelPath . '/app/Http/Kernel.php';
        copy($newHttpKernelFile, $httpKernelFile);

        // broadcast sample event
        $newbroadcastSampleEventFile = $assetsPath . '/BroadcastSampleEvent.php';
        $broadcastSampleEventFile = $this->laravelPath . '/app/Events/BroadcastSampleEvent.php';
        if (!file_exists($this->laravelPath . '/app/Events')) {
            mkdir($this->laravelPath . '/app/Events');
        }
        copy($newbroadcastSampleEventFile, $broadcastSampleEventFile);

        // conveyor config
        $newConveyorConfigFile = $assetsPath . '/conveyor-sample.php';
        $conveyorConfigFile = $this->laravelPath . '/config/conveyor.php';
        copy($newConveyorConfigFile, $conveyorConfigFile);

        // channels route
        $newChannelsRouteFile = $assetsPath . '/channels-sample.php';
        $channelsRouteFile = $this->laravelPath . '/routes/channels.php';
        copy($newChannelsRouteFile, $channelsRouteFile);

        // migrations
        $newDatabaseConfig = $assetsPath . '/database-sample.php';
        $databaseConfig = $this->laravelPath . '/config/database.php';
        copy($newDatabaseConfig, $databaseConfig);
        if (!file_exists($assetsPath . '/database/database.sqlite')) {
            touch($assetsPath . '/database/database.sqlite');
        }
        Process::path($this->laravelPath)
            ->run(['php', 'artisan', 'migrate']);

        // copy phpunit.xml
        $newPHPUnitXml = $assetsPath . '/phpunit-sample.xml';
        $phpUnitXml = $this->laravelPath . '/phpunit.xml';
        copy($newPHPUnitXml, $phpUnitXml);
    }

    private function recursiveChmod($path, $dirPermission, $filePermission) {
        if (is_dir($path)) {
            chmod($path, $dirPermission);
            $dir = new DirectoryIterator($path);
            foreach ($dir as $item) {
                if ($item->isDot()) continue;
                $this->recursiveChmod($item->getPathname(), $dirPermission, $filePermission);
            }
        } else {
            chmod($path, $filePermission);
        }
    }

    protected function getCookiesFromResponse(Response $response): array
    {
        $cookiesArray = $response->cookies()->toArray();
        $cookies = [];
        $domain = current($cookiesArray)['Domain'];
        foreach ($cookiesArray as $cookie) {
            $cookies[$cookie['Name']] = $cookie['Value'];
        }
        return [$cookies, $domain];
    }
}
