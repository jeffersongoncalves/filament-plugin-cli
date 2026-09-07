<?php

namespace App\Commands;

use JeffersonGoncalves\LaravelZero\SelfUpdate\PharUpdater;
use JeffersonGoncalves\LaravelZero\SelfUpdate\SelfUpdateCommand as BaseSelfUpdateCommand;

class SelfUpdateCommand extends BaseSelfUpdateCommand
{
    protected $description = 'Update the filament-plugin CLI to the latest version';

    protected function githubRepo(): string
    {
        return 'jeffersongoncalves/filament-plugin-cli';
    }

    protected function assetName(): string
    {
        return 'filament-plugin.phar';
    }

    protected function tempPrefix(): string
    {
        return 'filament_plugin_';
    }

    protected function currentVersion(): string
    {
        return (string) config('app.version', 'unreleased');
    }

    protected function makeUpdater(): PharUpdater
    {
        return $this->getLaravel()->make(PharUpdater::class);
    }
}
