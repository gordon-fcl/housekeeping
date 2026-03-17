<?php

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use FCL\Housekeeping\Providers\PackageServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            PackageServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
    }
}
