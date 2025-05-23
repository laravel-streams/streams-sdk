<?php

namespace Streams\Sdk;

use Streams\Core\Field\Field;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Streams\Sdk\Console\Inputs\ArrayConsoleInput;
use Streams\Sdk\Console\Inputs\ObjectConsoleInput;
use Streams\Sdk\Console\Inputs\SelectConsoleInput;
use Streams\Sdk\Console\Inputs\StringConsoleInput;
use Streams\Sdk\Console\Inputs\BooleanConsoleInput;
use Streams\Sdk\Console\Inputs\IntegerConsoleInput;

class SdkServiceProvider extends ServiceProvider
{
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Streams\Sdk\Console\Commands\MakeAddon::class,
                \Streams\Sdk\Console\Commands\MakeEntry::class,
                \Streams\Sdk\Console\Commands\MakeStream::class,
                \Streams\Sdk\Console\Commands\StreamsSchema::class,
                // \Streams\Sdk\Console\Commands\ShowEntry::class,      Necessary?
                // \Streams\Sdk\Console\Commands\ShowStream::class,     Necessary?
                // \Streams\Sdk\Console\Commands\ListEntries::class,    Necessary?
                // \Streams\Sdk\Console\Commands\ListStreams::class,    Necessary?
                //\Streams\Sdk\Console\Commands\DescribeStream::class,
            ]);
        }

        $this->publishes([
            __DIR__ . '/../streams/files.json' => base_path('streams/files.json'),
        ], ['examples', 'files-example']);
    }

    public function boot()
    {
        $this->registerInputs();

        Field::macro('console', function () {

            if (!App::has("streams.console.inputs.{$this->type}")) {
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
