<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use JeffersonGoncalves\LaravelZero\SelfUpdate\PharUpdater;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PharUpdater::class, fn () => new PharUpdater(
            githubRepo: 'jeffersongoncalves/filament-plugin-cli',
            assetName: 'filament-plugin.phar',
            tempPrefix: 'filament_plugin_',
            currentVersion: (string) config('app.version', 'unreleased'),
        ));
    }
}
