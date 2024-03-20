<?php

declare(strict_types=1);

namespace Ediblemanager\Housekeeping\Console\Commands;

use Illuminate\Console\Command;

use Ediblemanager\Housekeeping\Housekeeping;

class HousekeepingCommand extends Command
{
    protected $signature = "housekeeping:list";

    protected $description = "Fetch all issues matching the given tag";

    public function handle()
    {
        $housekeeping = new Housekeeping();
        $housekeeping->foo();
    }
}
