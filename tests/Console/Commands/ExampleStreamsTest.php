<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Streams\Core\Stream\Stream;
use Streams\Core\Support\Facades\Streams;
use Streams\Sdk\Tests\SdkTestCase;
use Symfony\Component\Process\Process;

/**
 * The example definitions in streams/ are published into apps
 * (php artisan vendor:publish --tag=examples), so they have to
 * validate and work with the generators.
 */
class ExampleStreamsTest extends SdkTestCase
{
    protected function getPackageProviders($app)
    {
        return [...parent::getPackageProviders($app), LivewireServiceProvider::class];
    }

    protected function examples(): array
    {
        return glob(dirname(__DIR__, 3) . '/streams/*.json');
    }

    /**
     * The published copies declare route handlers and filebase sources that no
     * test app defines, so leaving them in the skeleton changes which streams
     * every later test boots with. The generated components and views are
     * removed by SdkTestCase, which restores the generated-code directories to
     * the state they were in when the test started.
     */
    protected function tearDown(): void
    {
        $app = $this->app ?? null;

        if ($app !== null) {
            foreach ($this->examples() as $file) {
                $path = $app->basePath('streams/'.basename($file));

                if (is_file($path)) {
                    unlink($path);
                }
            }
        }

        parent::tearDown();
    }

    protected function publishExamples(): array
    {
        $paths = [];

        foreach ($this->examples() as $file) {
            copy($file, base_path('streams/' . basename($file)));

            $paths[] = 'streams/' . basename($file);
        }

        return $paths;
    }

    public function test_the_examples_are_published_under_the_examples_tag()
    {
        $published = \Illuminate\Support\ServiceProvider::pathsToPublish(
            \Streams\Sdk\SdkServiceProvider::class,
            'examples'
        );

        $this->assertCount(count($this->examples()), $published);
    }

    public function test_every_example_validates()
    {
        $paths = $this->publishExamples();

        $this->assertNotEmpty($paths);

        $this->artisan('streams:validate', ['paths' => $paths])
            ->expectsOutputToContain(count($paths) . ' checked, 0 invalid.')
            ->assertSuccessful();
    }

    public function test_the_livewire_generator_works_for_every_example()
    {
        $this->publishExamples();

        foreach ($this->examples() as $file) {

            $id = basename($file, '.json');

            Stream::resetMemory();

            Streams::load(base_path("streams/{$id}.json"));

            $namespace = 'App\\Livewire\\E' . bin2hex(random_bytes(4));

            config(['livewire.class_namespace' => $namespace]);

            $this->artisan('streams:livewire', ['stream' => $id])->assertSuccessful();

            $directory = app_path(str_replace('\\', '/', substr($namespace, 4)));

            foreach (glob($directory . '/*.php') as $class) {
                $lint = new Process([PHP_BINARY, '-l', $class]);
                $lint->run();
                $this->assertTrue($lint->isSuccessful(), $lint->getOutput() . $lint->getErrorOutput());

                require_once $class;
            }

            $studly = \Illuminate\Support\Str::studly($id);

            Livewire::test("{$namespace}\\{$studly}Index")->assertOk();
            Livewire::test("{$namespace}\\{$studly}Form")->assertOk();
        }
    }
}
