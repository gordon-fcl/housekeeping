<?php

namespace Ediblemanager\Housekeeping\Providers;

use Illuminate\Support\ServiceProvider;
use Ediblemanager\Housekeeping\Console\Commands\HousekeepingCommand;

class PackageServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register GrahamCampbell's GitHub package service provider
        $this->app->register(\GrahamCampbell\GitHub\GitHubServiceProvider::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([HousekeepingCommand::class]);
        }
    }
}
