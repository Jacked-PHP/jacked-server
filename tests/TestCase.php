<?php

namespace Tests;

use DirectoryIterator;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use JackedPhp\JackedServer\Services\Server;
use OpenSwoole\Process as OpenSwooleProcess;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Http\Client\Response;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected $loadEnvironmentVariables = false;

    public string $laravelPath;
    public int $port = 8989;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLaravel();
    }

    protected function startServer(
        ?string $inputFile = null,
        ?string $documentRoot = null,
    ): int {
        Config::set(
            'jacked-server.input-file',
            $inputFile ?? rtrim(__DIR__, '/Unit') . '/Assets/laravel/public/index.php',
        );
        Config::set(
            'jacked-server.openswoole-server-settings.document_root',
            $documentRoot ?? rtrim(__DIR__, '/Unit') . '/Assets/laravel/public',
        );
        Config::set(
            'jacked-server.openswoole-server-settings.static_handler_locations',
            ['/css', '/js', '/img'],
        );

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
        sleep(2); // some instances take a little more time to get up and running

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
        $assetsPath = __DIR__ . '/Assets';

        $this->laravelPath = $assetsPath . '/laravel';
        if (!file_exists($this->laravelPath)) {
            Process::run(['composer', 'create-project', 'laravel/laravel', $this->laravelPath]);
            $this->recursiveChmod($this->laravelPath, 0777, 0777);
        }

        // web route
        $newWebRouteFile = $assetsPath . '/sample-web.php';
        $webRouteFile = $this->laravelPath . '/routes/web.php';
        copy($newWebRouteFile, $webRouteFile);

        // api route
        $newApiRouteFile = $assetsPath . '/sample-api.php';
        $apiRouteFile = $this->laravelPath . '/routes/api.php';
        copy($newApiRouteFile, $apiRouteFile);
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
