<?php

namespace Streams\Sdk;

use Streams\Core\Field\Field;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Streams\Sdk\Support\DocsIndex;
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
        $this->mergeConfigFrom(__DIR__ . '/../resources/config/sdk.php', 'streams.sdk');

        $this->app->singleton(DocsIndex::class, fn () => new DocsIndex(
            (array) Config::get('streams.sdk.mcp.docs.paths', []),
            base_path()
        ));

        $this->publishes([
            __DIR__ . '/../resources/config/sdk.php' => config_path('streams/sdk.php'),
        ], 'streams-sdk-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Streams\Sdk\Console\Commands\MakeAddon::class,
                \Streams\Sdk\Console\Commands\MakeEntry::class,
                \Streams\Sdk\Console\Commands\MakeStream::class,
                \Streams\Sdk\Console\Commands\MakeLivewire::class,
                \Streams\Sdk\Console\Commands\StreamsSchema::class,
                // \Streams\Sdk\Console\Commands\ShowEntry::class,      Necessary?
                // \Streams\Sdk\Console\Commands\ShowStream::class,     Necessary?
                // \Streams\Sdk\Console\Commands\ListEntries::class,    Necessary?
                // \Streams\Sdk\Console\Commands\ListStreams::class,    Necessary?
                //\Streams\Sdk\Console\Commands\DescribeStream::class,
                \Streams\Sdk\Console\Commands\ListStreams::class,
                \Streams\Sdk\Console\Commands\StreamsValidate::class,
            ]);
        }

        $this->publishes([
            __DIR__ . '/../streams/files.json' => base_path('streams/files.json'),
        ], ['examples', 'files-example']);

        $this->publishes([
            __DIR__ . '/../streams/blog_posts.json' => base_path('streams/blog_posts.json'),
        ], ['examples', 'blog-example']);

        $this->publishes([
            __DIR__ . '/../streams/products.json' => base_path('streams/products.json'),
        ], ['examples', 'ecommerce-example']);

        $this->publishes([
            __DIR__ . '/../streams/contacts.json' => base_path('streams/contacts.json'),
        ], ['examples', 'crm-example']);

        $this->publishes([
            __DIR__ . '/../streams/docs.json' => base_path('streams/docs.json'),
        ], ['examples', 'docs-example']);
    }

    public function boot()
    {
        $this->registerInputs();
        $this->registerMcpServer();

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

    /**
     * Register the local-development MCP server
     * when laravel/mcp is installed.
     */
    protected function registerMcpServer()
    {
        if (!class_exists(\Laravel\Mcp\Server::class) || !Config::get('streams.sdk.mcp.enabled', true)) {
            return;
        }

        if ($this->app->environment('production') && !Config::get('streams.sdk.mcp.allow_production', false)) {
            return;
        }

        \Laravel\Mcp\Facades\Mcp::local(
            Config::get('streams.sdk.mcp.handle', 'streams'),
            \Streams\Sdk\Mcp\StreamsServer::class
        );
    }
}
