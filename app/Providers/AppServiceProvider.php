<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! extension_loaded('intl') && ! class_exists(\Illuminate\Support\Number::class, false)) {
            require_once app_path('Support/NumberFallback.php');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('database.default') === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database');
            if (is_string($dbPath) && $dbPath !== ':memory:' && ! file_exists($dbPath) && is_dir(dirname($dbPath))) {
                @touch($dbPath);
            }
        }

        if (! $this->app->runningInConsole()) {
            $baseUrl = rtrim(request()->getBaseUrl(), '/');
            config(['filesystems.disks.public.url' => $baseUrl . '/media?file=']);
        }
    }
}
