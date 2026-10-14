<?php

namespace Streams\Sdk\Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Tests\SdkTestCase;

class CatalogCheckTest extends SdkTestCase
{
    protected string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/streams-catalog-check-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    protected function snapshot(): array
    {
        $snapshot = [];
        foreach (File::allFiles($this->directory) as $file) {
            $snapshot[$file->getRelativePathname()] = [file_get_contents($file->getPathname()), $file->getMTime()];
        }

        return $snapshot;
    }

    public function test_check_succeeds_without_changing_current_files(): void
    {
        $this->assertSame(0, Artisan::call('streams:catalog', ['--path' => $this->directory]));
        $before = $this->snapshot();
        $this->assertSame(0, Artisan::call('streams:catalog', ['--path' => $this->directory, '--check' => true]));
        $this->assertStringContainsString('Catalog is up to date.', Artisan::output());
        $this->assertSame($before, $this->snapshot());
    }

    public function test_check_fails_on_drift_without_changing_any_files(): void
    {
        $this->assertSame(0, Artisan::call('streams:catalog', ['--path' => $this->directory]));
        foreach (['catalog/catalog.json', 'catalog/catalog.schema.json', 'schemas/streams.schema.json'] as $file) {
            $original = File::get($this->directory . '/' . $file);
            File::put($this->directory . '/' . $file, "stale\n");
            $before = $this->snapshot();
            $this->assertSame(1, Artisan::call('streams:catalog', ['--path' => $this->directory, '--check' => true]));
            $this->assertStringContainsString('drift: ' . $this->directory . '/' . $file, Artisan::output());
            $this->assertSame($before, $this->snapshot());
            File::put($this->directory . '/' . $file, $original);
        }
    }

    public function test_check_does_not_create_missing_files_or_directories(): void
    {
        $this->assertSame(1, Artisan::call('streams:catalog', ['--path' => $this->directory, '--check' => true]));
        $this->assertDirectoryDoesNotExist($this->directory);

        File::ensureDirectoryExists($this->directory . '/catalog');
        File::put($this->directory . '/catalog/catalog.json', "stale\n");
        $before = $this->snapshot();
        $this->assertSame(1, Artisan::call('streams:catalog', ['--path' => $this->directory, '--check' => true]));
        $this->assertSame($before, $this->snapshot());
        $this->assertDirectoryDoesNotExist($this->directory . '/schemas');
    }
}
