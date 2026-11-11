<?php

namespace Streams\Sdk;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Streams\Core\Field\Field;
use Streams\Sdk\Console\Commands\CatalogGenerate;
use Streams\Sdk\Console\Commands\ListEntries;
use Streams\Sdk\Console\Commands\ListStreams;
use Streams\Sdk\Console\Commands\ShowEntry;
use Streams\Sdk\Console\Commands\StreamsCapture;
use Streams\Sdk\Console\Commands\StreamsMake;
use Streams\Sdk\Console\Commands\StreamsSchema;
use Streams\Sdk\Console\Commands\StreamsTap;
use Streams\Sdk\Console\Commands\StreamsOnboard;
use Streams\Sdk\Console\Commands\StreamsValidate;
use Streams\Sdk\Console\Commands\StubPublish;
use Streams\Sdk\Console\Inputs\ArrayConsoleInput;
use Streams\Sdk\Console\Inputs\BooleanConsoleInput;
use Streams\Sdk\Console\Inputs\IntegerConsoleInput;
use Streams\Sdk\Console\Inputs\ObjectConsoleInput;
use Streams\Sdk\Console\Inputs\SelectConsoleInput;
use Streams\Sdk\Console\Inputs\StringConsoleInput;
use Streams\Sdk\Console\Make\Kinds\KindRegistry;

class SdkServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(KindRegistry::class, function () {
            return KindRegistry::withBuiltIns()->loadConfigured();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                StreamsMake::class,
                StreamsOnboard::class,
                StreamsCapture::class,
                CatalogGenerate::class,
                StreamsSchema::class,
                StreamsTap::class,
                StreamsValidate::class,
                StubPublish::class,
                ShowEntry::class,
                ListEntries::class,
                ListStreams::class,
                // \Streams\Sdk\Console\Commands\ShowStream::class,     Necessary?
                // \Streams\Sdk\Console\Commands\DescribeStream::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../streams/files.json' => base_path('streams/files.json'),
        ], ['examples', 'files-example']);

        $this->publishes([
            __DIR__.'/Console/Commands/stubs' => (string) config('streams.sdk.make.stubs_dir', base_path('streams/stubs')),
        ], ['stubs', 'streams-stubs']);
    }

    public function boot()
    {
        $this->registerInputs();

        Field::macro('console', function () {

            if (! App::has("streams.console.inputs.{$this->type}")) {
                throw new \Exception("Missing SDK input [{$this->type}] required for field [{$this->handle}] in stream [{$this->stream->id}]");
            }

            return App::make("streams.console.inputs.{$this->type}", ['field' => $this]);
        });

        // public function factory(string $id): EntryFactory
        // {
        //     return $this
        //         ->make($id)
        //         ->factory();
        // }

        // public function factory(): EntryFactory
        // {
        //     return static::once($this->id . __METHOD__, fn () => $this->newFactory());
        // }

        // protected function newFactory(): EntryFactory
        // {
        //     $factory  = $this->config('factory', EntryFactory::class);

        //     return new $factory($this);
        // }

        // public function generate()
        // {
        //     return $this->generator()->text();
        // }

        // public function generator()
        // {
        //     // @todo app(this->config('generator))
        //     return $this->once(__METHOD__, fn () => \Faker\Factory::create());
        // }

        // public function factory(): Factory
        // {
        //     $factory = $this->config('factory', $this->getFactoryName());

        //     return new $factory($this);
        // }

        // protected function getFactoryName()
        // {
        //     return Factory::class;
        // }
    }

    protected function registerInputs()
    {
        $inputs = Config::get('streams.console.inputs', [
            'uuid' => StringConsoleInput::class,
            'string' => StringConsoleInput::class,
            'slug' => StringConsoleInput::class,
            'email' => StringConsoleInput::class,
            'url' => StringConsoleInput::class,
            'hash' => StringConsoleInput::class,
            'file' => StringConsoleInput::class,
            'image' => StringConsoleInput::class,
            'integer' => IntegerConsoleInput::class,
            'object' => ObjectConsoleInput::class,
            'array' => ArrayConsoleInput::class,
            'enum' => SelectConsoleInput::class,
            'select' => SelectConsoleInput::class,
            'boolean' => BooleanConsoleInput::class,

            'datetime' => StringConsoleInput::class,
            'date' => StringConsoleInput::class,
            'time' => StringConsoleInput::class,

            'relationship' => StringConsoleInput::class,

            'number' => StringConsoleInput::class,
            'decimal' => StringConsoleInput::class,
            'integer' => StringConsoleInput::class,
        ]);

        foreach ($inputs as $abstract => $concrete) {
            $this->app->bind("streams.console.inputs.{$abstract}", $concrete);
        }
    }
}
