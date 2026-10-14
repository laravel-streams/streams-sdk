<?php

namespace Streams\Sdk\Console\Support;

namespace Streams\Sdk\Console\Support;

use Illuminate\Support\Facades\File;

/**
 * Resolves and renders the versioned stub set shipped with the SDK.
 *
 * Stubs live under src/Console/Commands/stubs/{name}/{version}/{file}.stub.
 * The current version is resolved here so a new stub version can be added
 * without touching the commands that consume it.
 *
 * An app that runs `streams:stub:publish` gets the same set copied under
 * config('streams.sdk.make.stubs_dir'); a published stub is preferred over
 * the SDK copy so apps can override generated output for these trivial files.
 */
class Stub
{
    public const VERSION = 'v1';

    public function __construct(
        protected string $name,
        protected array $data = [],
        protected string $version = self::VERSION,
    ) {
    }

    /**
     * Make a new stub instance.
     *
     * "table" resolves to stubs/table/v1/table.stub and
     * "table.registry" to stubs/table/v1/registry.stub.
     */
    public static function make(string $name, array $data = [], ?string $version = null): static
    {
        return new static($name, $data, $version ?: static::VERSION);
    }

    public function path(): string
    {
        [$directory, $file] = array_pad(explode('.', $this->name, 2), 2, null);

        $relative = $directory .
            '/' .
            $this->version .
            '/' .
            ($file ?: $directory) .
            '.stub';

        $published = $this->publishedDir() . '/' . $relative;

        if (File::exists($published)) {
            return $published;
        }

        return __DIR__ . '/../Commands/stubs/' . $relative;
    }

    /**
     * The app stub directory published stubs are copied to.
     */
    protected function publishedDir(): string
    {
        return rtrim((string) config('streams.sdk.make.stubs_dir', base_path('streams/stubs')), '/');
    }

    public function version(): string
    {
        return $this->version;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function with(array $data): static
    {
        return new static($this->name, array_merge($this->data, $data), $this->version);
    }

    /**
     * Render the stub with its data applied.
     *
     * Placeholders are declared as {{ $key }} in the stub files.
     */
    public function render(): string
    {
        $contents = file_get_contents($this->path());

        foreach ($this->data as $key => $value) {
            $contents = str_replace('{{ $' . $key . ' }}', (string) $value, $contents);
        }

        return trim($contents);
    }
}