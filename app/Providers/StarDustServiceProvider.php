<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use StarDust\StarDust;
use StarDust\Config\Config;
use PDO;

class StarDustServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(StarDust::class, function ($app) {
            $pdo = DB::connection()->getPdo();
            // Ensure PDO error mode is exception
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $logger = \Illuminate\Support\Facades\Log::getLogger();
            $config = new Config(pdo: $pdo, logger: $logger);
            return new StarDust($config);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
