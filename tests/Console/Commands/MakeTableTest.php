<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Illuminate\Support\Facades\File;
use Streams\Sdk\Tests\SdkTestCase;
use Streams\Sdk\Console\Support\Pint;

class MakeTableTest extends SdkTestCase
{
    protected string $tablesDir;

    protected string $registryPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tablesDir = sys_get_temp_dir() . '/streams-sdk-tables-' . uniqid();

        $this->registryPath = $this->tablesDir . '/tables.php';

        config([
            'streams.sdk.tables_dir' => $this->tablesDir,
            'streams.sdk.tables_path' => $this->registryPath,
        ]);

        File::ensureDirectoryExists($this->tablesDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tablesDir);

        parent::tearDown();
    }

    public function test_it_makes_a_table_and_registers_it_in_the_panel_and_on_the_page()
    {
        $this->artisan('make:table', ['id' => 'posts', '--panel' => true, '--page' => true])
            ->assertSuccessful();

        $this->assertFileExists($this->tablesDir . '/posts.json');
        $this->assertFileExists($this->registryPath);

        $this->assertStringContainsString('"handle": "posts"', File::get($this->tablesDir . '/posts.json'));

        $registry = File::get($this->registryPath);

        $this->assertStringContainsString("'posts' => ['panel' => true, 'page' => true]", $registry);
        $this->assertStringContainsString('table registry stub v1', $registry);
    }

    public function test_it_is_idempotent()
    {
        $this->artisan('make:table', ['id' => 'posts', '--panel' => true])->assertSuccessful();

        $first = File::get($this->registryPath);

        $this->artisan('make:table', ['id' => 'posts', '--panel' => true])
            ->expectsOutputToContain('no changes')
            ->assertSuccessful();

        $this->assertSame($first, File::get($this->registryPath));
    }

    public function test_dry_run_shows_the_diff_the_real_run_applies_without_writing()
    {
        $this->artisan('make:table', ['id' => 'posts', '--panel' => true, '--page' => true, '--dry-run' => true])
            ->expectsOutputToContain("'posts' => ['panel' => true, 'page' => true]")
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->tablesDir . '/posts.json');
        $this->assertFileDoesNotExist($this->registryPath);

        // The real run applies exactly the content the dry run described.
        $this->artisan('make:table', ['id' => 'posts', '--panel' => true, '--page' => true])
            ->assertSuccessful();

        $registry = File::get($this->registryPath);

        $this->assertStringContainsString("'posts' => ['panel' => true, 'page' => true]", $registry);
    }

    public function test_it_updates_flags_in_place_in_an_existing_registry()
    {
        $this->artisan('make:table', ['id' => 'posts', '--panel' => true])->assertSuccessful();

        $this->artisan('make:table', ['id' => 'posts', '--page' => true])->assertSuccessful();

        $registry = File::get($this->registryPath);

        $this->assertStringContainsString("'posts' => ['panel' => true, 'page' => true]", $registry);
    }

    public function test_it_keeps_existing_entries_when_registering_more_tables()
    {
        $this->artisan('make:table', ['id' => 'posts', '--panel' => true])->assertSuccessful();

        $this->artisan('make:table', ['id' => 'authors', '--page' => true])->assertSuccessful();

        $registry = File::get($this->registryPath);

        $this->assertStringContainsString("'posts' => ['panel' => true]", $registry);
        $this->assertStringContainsString("'authors' => ['page' => true]", $registry);
    }

    public function test_written_registry_is_pint_formatted_and_stable()
    {
        $this->artisan('make:table', ['id' => 'posts', '--panel' => true])
            ->expectsOutputToContain('(pint)')
            ->assertSuccessful();

        $registry = File::get($this->registryPath);

        // The post-step ran Pint on the written registry.
        $this->assertStringContainsString("'posts' => [", $registry);
        $this->assertStringEndsWith("\n", $registry);

        // Pint is stable: a second pass leaves the file byte-for-byte identical.
        $this->assertTrue(Pint::format($this->registryPath));
        $this->assertSame($registry, File::get($this->registryPath));
    }

    public function test_it_rejects_invalid_table_ids()
    {
        $this->artisan('make:table', ['id' => 'Not Valid!'])
            ->assertFailed();

        $this->assertFileDoesNotExist($this->tablesDir . '/Not Valid!.json');
    }
}