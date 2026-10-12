<?php

namespace Streams\Sdk\Console\Support;

/**
 * Resolves and renders the versioned stub set shipped with the SDK.
 *
 * Stubs live under src/Console/Commands/stubs/{name}/{version}/{file}.stub.
 * The current version is resolved here so a new stub version can be added
 * without touching the commands that consume it.
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

        return __DIR__ .
            '/../Commands/stubs/' .
            $directory .
            '/' .
            $this->version .
            '/' .
            ($file ?: $directory) .
            '.stub';
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