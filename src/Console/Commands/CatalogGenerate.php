<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Support\CatalogGenerator;

class CatalogGenerate extends Command
{
    protected $signature = 'streams:catalog
        {--path= : The directory to write catalog files into.}';

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

        foreach ($files as $file => $payload) {
            File::ensureDirectoryExists(dirname($file));

            File::put(
                $file,
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
            );

            $this->line("<info>written:</info> {$file}");
        }

        $this->line("cataloged {$fieldTypes} field types and {$builders} UI builders.");

        return self::SUCCESS;
    }
}