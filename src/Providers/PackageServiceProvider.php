<?php

declare(strict_types=1);

namespace FCL\Housekeeping\Providers;

use FCL\Housekeeping\Console\Commands\ExportCommand;
use FCL\Housekeeping\Console\Commands\HousekeepingCommand;
use FCL\Housekeeping\Console\Commands\ShowCommand;
use FCL\Housekeeping\Console\Commands\StartCommand;
use FCL\Housekeeping\Housekeeping;
use Illuminate\Support\ServiceProvider;

class PackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/housekeeping.php', 'housekeeping');

        $this->configureGitHub();

        $this->app->register(\GrahamCampbell\GitHub\GitHubServiceProvider::class);

        $this->app->singleton(Housekeeping::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/housekeeping.php' => config_path('housekeeping.php'),
            ], 'housekeeping-config');

            $this->commands([
                HousekeepingCommand::class,
                ShowCommand::class,
                StartCommand::class,
                ExportCommand::class,
            ]);
        }
    }

    /**
     * Set sensible GitHub defaults so the consuming app only needs
     * GITHUB_TOKEN in .env to get started.
     */
    private function configureGitHub(): void
    {
        $config = $this->app->make('config');

        if ($config->get('github')) {
            return;
        }

        $config->set('github', [
            'default' => 'main',
            'connections' => [
                'main' => [
                    'method' => 'token',
                    'token' => env('GITHUB_TOKEN', ''),
                ],
            ],
        ]);
    }
}
