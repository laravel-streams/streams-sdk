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
    /**
     * Paths created inside the test application by withFixtureStreams().
     *
     * @var list<string>
     */
    protected array $fixturePaths = [];

    /**
     * Files present in the generated-code directories before the test ran.
     *
     * @var array<string, list<string>>
     */
    protected array $generatedSnapshot = [];

    /**
     * Directories the generators write application code into.
     *
     * @return list<string>
     */
    protected function generatedCodePaths(): array
    {
        return [
            $this->app->path('Livewire'),
            $this->app->resourcePath('views/livewire'),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generatedSnapshot = $this->snapshotGeneratedCode();
    }

    /**
     * @return array<string, list<string>>
     */
    protected function snapshotGeneratedCode(): array
    {
        $snapshot = [];

        foreach ($this->generatedCodePaths() as $directory) {
            if (! is_dir($directory)) {
                $snapshot[$directory] = [];

                continue;
            }

            $files = iterator_to_array(new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            ));

            $snapshot[$directory] = array_values(array_map(
                fn (\SplFileInfo $file) => str_replace($directory.'/', '', $file->getPathname()),
                array_filter($files, fn (\SplFileInfo $file) => $file->isFile())
            ));
        }

        return $snapshot;
    }

    /**
     * Remove generated code the test added and drop compiled views.
     *
     * The generators write into the shared Testbench skeleton, so a file one test
     * class leaves behind is read back by the next class - a stale Blade view or
     * component changes what a freshly generated component validates and renders,
     * which is why results depended on which class ran first. Restoring the
     * snapshot keeps every class order-independent without deleting the skeleton
     * directories themselves.
     */
    protected function restoreGeneratedCode(): void
    {
        foreach ($this->generatedSnapshot as $directory => $existing) {
            if (! is_dir($directory)) {
                continue;
            }

            $files = iterator_to_array(new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            ));

            foreach ($files as $file) {
                if ($file->isFile() && ! in_array(str_replace($directory.'/', '', $file->getPathname()), $existing, true)) {
                    unlink($file->getPathname());
                }
            }

            // Directories created during the test (random namespaces): remove the
            // now-empty ones so the tree matches what it was.
            foreach (array_reverse(glob($directory.'/*', GLOB_ONLYDIR) ?: []) as $subdirectory) {
                if (! in_array(basename($subdirectory), $this->snapshotDirectories($directory, $existing), true)) {
                    @rmdir($subdirectory);
                }
            }
        }

        foreach (glob($this->app->storagePath('framework/views/*.php')) ?: [] as $compiled) {
            unlink($compiled);
        }
    }

    /**
     * @param  list<string>  $existing
     * @return list<string>
     */
    protected function snapshotDirectories(string $directory, array $existing): array
    {
        return array_values(array_unique(array_map(
            fn (string $path) => explode('/', $path)[0],
            array_filter($existing, fn (string $path) => str_contains($path, '/'))
        )));
    }

    protected function getPackageProviders($app)
    {
        return [
            StreamsServiceProvider::class,
            SdkServiceProvider::class,
        ];
    }

    /**
     * Copy fixture stream definitions - and the filebase entries they read -
     * into the running application, then load them.
     *
     * Core resolves a filebase source against the application, not the package,
     * so tests/fixtures/streams only works once it is where an app would keep it.
     * Everything written here is removed again in tearDown, because a leftover
     * definition changes which streams every later test boots with.
     *
     * @param  list<string>  $names  fixture basenames without extension, e.g. ['posts']
     */
    protected function withFixtureStreams(string ...$names): void
    {
        $fixtures = dirname(__DIR__).'/tests/fixtures/streams';

        foreach ($names as $name) {
            $target = $this->app->basePath("streams/{$name}.json");

            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0777, true);
                $this->fixturePaths[] = dirname($target);
            }

            copy("{$fixtures}/{$name}.json", $target);
            $this->fixturePaths[] = $target;

            if (is_dir("{$fixtures}/data/{$name}")) {
                $dataTarget = $this->app->basePath("streams/data/{$name}");

                if (! is_dir(dirname($dataTarget))) {
                    mkdir(dirname($dataTarget), 0777, true);
                    $this->fixturePaths[] = dirname($dataTarget);
                }

                $this->copyDirectory("{$fixtures}/data/{$name}", $dataTarget);
                $this->fixturePaths[] = $dataTarget;
            }

            \Streams\Core\Support\Facades\Streams::load($target);
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse(array_unique($this->fixturePaths)) as $path) {
            if (is_dir($path)) {
                $entries = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($entries as $entry) {
                    $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
                }
            }

            if (is_file($path)) {
                unlink($path);
            }

            if (is_dir($path)) {
                rmdir($path);
            }
        }

        $this->fixturePaths = [];

        if ($this->app ?? null) {
            $this->restoreGeneratedCode();
        }

        // Stream objects memoize per stream id on process statics, and fields
        // memoize on Field::$memory keyed by handle alone (SelectFieldType::once
        // ("{$handle}.options")), which outlive the per-test application. Without
        // clearing both, a stream registered by one test supplies field config to
        // the next test's app - observed as a generated form component carrying
        // both the `posts` and `blog_posts` `status` options in one rules array.
        \Streams\Core\Stream\Stream::resetMemory();
        \Streams\Core\Field\Field::resetMemory();

        parent::tearDown();
    }

    protected function copyDirectory(string $from, string $to): void
    {
        mkdir($to, 0777, true);

        foreach (scandir($from) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $source = "{$from}/{$entry}";

            is_dir($source)
                ? $this->copyDirectory($source, "{$to}/{$entry}")
                : copy($source, "{$to}/{$entry}");
        }
    }
}
