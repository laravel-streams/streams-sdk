<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Console\Commands\MakeTable;
use Streams\Sdk\Console\Commands\StubPublish;
use Streams\Sdk\Tests\SdkTestCase;

class StubPublishTest extends SdkTestCase
{
    protected string $stubsDir;

    protected string $tablesDir;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = $this->app->make(Kernel::class);

        $kernel->registerCommand($this->app->make(StubPublish::class));
        $kernel->registerCommand($this->app->make(MakeTable::class));

        $this->stubsDir = sys_get_temp_dir() . '/streams-sdk-stubs-' . uniqid();
        $this->tablesDir = sys_get_temp_dir() . '/streams-sdk-tables-' . uniqid();

        config([
            'streams.sdk.make.stubs_dir' => $this->stubsDir,
            'streams.sdk.tables_dir' => $this->tablesDir,
            'streams.sdk.tables_path' => $this->tablesDir . '/tables.php',
        ]);

        File::ensureDirectoryExists($this->tablesDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->stubsDir);
        File::deleteDirectory($this->tablesDir);

        parent::tearDown();
    }

    public function test_it_skips_existing_files_unless_forced()
    {
        $this->artisan('streams:stub:publish')->assertSuccessful();

        $table = $this->stubsDir . '/table/v1/table.stub';

        $this->assertFileExists($table);
        $this->assertFileExists($this->stubsDir . '/composer.stub');

        // The published copy matches the SDK stub.
        $this->assertSame($this->sdkStub('table/v1/table.stub'), File::get($table));

        // A second run skips the now-existing files.
        $this->artisan('streams:stub:publish')
            ->expectsOutputToContain('exists (skipped)')
            ->assertSuccessful();

        // Skipped files keep the app's content, not the SDK copy.
        File::put($table, "app change\n");

        $this->artisan('streams:stub:publish')
            ->expectsOutputToContain('exists (skipped)')
            ->assertSuccessful();

        $this->assertSame("app change\n", File::get($table));
    }

    public function test_it_replaces_published_stubs_with_force()
    {
        $this->artisan('streams:stub:publish')->assertSuccessful();

        File::put($this->stubsDir . '/table/v1/table.stub', "replacement\n");

        $this->artisan('streams:stub:publish', ['--force' => true])
            ->assertSuccessful();

        $this->assertSame(
            $this->sdkStub('table/v1/table.stub'),
            File::get($this->stubsDir . '/table/v1/table.stub')
        );
    }

    public function test_it_refuses_to_publish_into_the_sdk_source_directory()
    {
        config(['streams.sdk.make.stubs_dir' => dirname(__DIR__, 3) . '/src/Console/Commands/stubs']);

        $this->artisan('streams:stub:publish')
            ->expectsOutputToContain('Refusing to publish stubs')
            ->assertFailed();
    }

    public function test_a_published_stub_overrides_the_generated_output()
    {
        $this->artisan('streams:stub:publish')->assertSuccessful();

        // The app changes the shipped table stub.
        File::put(
            $this->stubsDir . '/table/v1/table.stub',
            <<<STUB
            {
                "\$schema": "https://streams.dev/schema/streams.schema.json",
                "\$comment": "app override",
                "name": "{{ \$name }}",
                "handle": "{{ \$id }}"
            }
            STUB . "\n",
        );

        $this->artisan('make:table', ['id' => 'posts', '--panel' => true])
            ->assertSuccessful();

        $output = File::get($this->tablesDir . '/posts.json');

        $this->assertStringContainsString('"$comment": "app override"', $output);
        $this->assertStringContainsString('"handle": "posts"', $output);
        $this->assertStringNotContainsString('table stub v1', $output);
    }

    /**
     * The SDK's own copy of a relative stub path.
     */
    protected function sdkStub(string $relative): string
    {
        return (string) File::get(
            __DIR__ . '/../../../src/Console/Commands/stubs/' . $relative
        );
    }
}
