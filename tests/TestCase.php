<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Phoenix1331\FilamentHeadroom\FilamentHeadroomServiceProvider;
use Phoenix1331\FilamentHeadroom\Tests\Fixtures\TestPanelProvider;

abstract class TestCase extends Orchestra
{
    protected $enablesPackageDiscoveries = true;

    protected function getPackageProviders($app): array
    {
        return [
            FilamentHeadroomServiceProvider::class,
            TestPanelProvider::class,
        ];
    }
}
