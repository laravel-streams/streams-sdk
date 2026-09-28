<?php

namespace Streams\Sdk\Tests;

use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use Streams\Core\Stream\Stream;
use Streams\Sdk\SdkServiceProvider;
use Streams\Core\StreamsServiceProvider;

/**
 * Boots each test in a fresh copy of the Testbench Laravel
 * skeleton with the stream, entry, and docs fixtures in
 * tests/fixtures, so tests can write files freely.
 */
abstract class SdkTestCase extends TestCase
{
    protected static ?string $basePath = null;

    protected function setUp(): void
    {
        static::$basePath = $this->makeSkeleton();

        Stream::resetMemory();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (static::$basePath) {
            (new Filesystem)->deleteDirectory(static::$basePath);
        }

        static::$basePath = null;
    }

    public static function applicationBasePath()
    {
        return static::$basePath ?? parent::applicationBasePath();
    }

    protected function getPackageProviders($app)
    {
        $providers = [StreamsServiceProvider::class];

        if (class_exists(\Laravel\Mcp\Server\McpServiceProvider::class)) {
            $providers[] = \Laravel\Mcp\Server\McpServiceProvider::class;
        }

        $providers[] = SdkServiceProvider::class;

        return $providers;
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('app.debug', false);
    }

    protected function makeSkeleton(): string
    {
        $path = sys_get_temp_dir() . '/streams-sdk-tests/' . uniqid('app_', true);

        $files = new Filesystem;

        $files->copyDirectory(parent::applicationBasePath(), $path);

        $files->copyDirectory(__DIR__ . '/fixtures/streams', $path . '/streams');
        $files->copyDirectory(__DIR__ . '/fixtures/docs', $path . '/docs');

        $files->put($path . '/composer.lock', json_encode(['packages' => [], 'packages-dev' => []]));

        return $path;
    }

    protected function basePath(string $path = ''): string
    {
        return static::$basePath . ($path ? '/' . ltrim($path, '/') : '');
    }
}
