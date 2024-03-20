<?php

namespace Ediblemanager\Housekeeping\Providers;

use Illuminate\Support\ServiceProvider;
use Ediblemanager\Housekeeping\Console\Commands\HousekeepingCommand;

class PackageServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->commands([GreetCommand::class]);
    }
}
