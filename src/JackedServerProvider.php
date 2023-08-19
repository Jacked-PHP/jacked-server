<?php

namespace JackedPhp\JackedServer;

use Illuminate\Support\ServiceProvider;
use JackedPhp\JackedServer\Commands\OpenSwooleServer;

class JackedServerProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/jacked-server.php', 'jacked-server');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/jacked-server.php' => config_path('jacked-server.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                OpenSwooleServer::class,
            ]);
        }
    }
}
