<?php

namespace YousefKadah\BackoffJitter\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use YousefKadah\BackoffJitter\BackoffJitterServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BackoffJitterServiceProvider::class];
    }
}
