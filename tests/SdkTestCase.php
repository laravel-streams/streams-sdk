<?php

namespace Streams\Sdk\Tests;

use Orchestra\Testbench\TestCase as TestbenchTestCase;
use Streams\Core\StreamsServiceProvider;
use Streams\Sdk\SdkServiceProvider;

/**
 * Boots a real application with Core and the SDK.
 *
 * This extended Streams\Testing\TestCase, which pins orchestra/testbench ^8.36
 * (Laravel 10) and cannot install beside this package's testbench ^10 and
 * Laravel 12 dev requirements, so every test file that extended it fataled on
 * class load and CI never ran. The scaffolding that base class added lives in
 * its own laravel/ fixture, which no SDK test uses.
 */
abstract class SdkTestCase extends TestbenchTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            StreamsServiceProvider::class,
            SdkServiceProvider::class,
        ];
    }
}
