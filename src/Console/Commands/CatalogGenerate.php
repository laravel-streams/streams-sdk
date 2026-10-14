<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Support\CatalogGenerator;

class CatalogGenerate extends Command
{
    protected $signature = 'streams:catalog
        {--path= : The directory containing catalog files.}
        {--check : Check for drift without writing any files.}';

    protected $description = 'Generate the machine-readable SDK catalog, its schema, and streams.schema.json.';

    public function handle(): int
    {
        $generator = new CatalogGenerator();

        $path = rtrim((string) $this->option('path'), '/');

        if ($path === '') {
            $path = __DIR__ . '/../../../resources';
        }

        $files = [
            "$path/catalog/catalog.json" => $generator->catalog(),
            "$path/catalog/catalog.schema.json" => $generator->catalogSchema(),
            "$path/schemas/streams.schema.json" => $generator->streamDefinitionSchema(),
        ];

        $fieldTypes = count($generator->fieldTypes());
        $builders = count($generator->uiBuilders());

        $drift = false;

        foreach ($files as $file => $payload) {
            $contents = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

            if ($this->option('check')) {
                if (!File::exists($file) || File::get($file) !== $contents) {
                    $drift = true;
                    $this->error("drift: {$file}");
                }

                continue;
            }

            File::ensureDirectoryExists(dirname($file));

            File::put($file, $contents);

            $this->line("<info>written:</info> {$file}");
        }

        if ($this->option('check')) {
            if ($drift) {
                $this->error('Catalog is stale. Run streams:catalog to regenerate the files.');

                return self::FAILURE;
            }

            $this->info('Catalog is up to date.');

            return self::SUCCESS;
        }

        $this->line("cataloged {$fieldTypes} field types and {$builders} UI builders.");

        return self::SUCCESS;
    }
}
