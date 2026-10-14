<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Streams\Core\Stream\Stream;
use Streams\Core\Support\Facades\Streams;

class StreamsSchema extends Command
{
    protected $signature = 'streams:schema
        {--include= : Comma seperated streams to include.}
        {--exclude= : Comma seperated streams to exclude.}
        {--path= : The path to write files to.}';

    protected $description = 'Write per-stream schema files and the app-definition schema.';

    public function handle()
    {
        $streams = Streams::collection();

        if ($include = $this->option('include')) {
            $streams = $streams->whereIn('id', explode(',', $include));
        }

        if ($exclude = $this->option('exclude')) {
            $streams = $streams->whereNotIn('id', explode(',', $exclude));
        }

        $path = $this->resolvePath($this->option('path'));

        foreach ($streams as $stream) {
            try {
                $this->writeSchemaFile($stream, $path);
            } catch (\Throwable $e) {
                $this->warn("Skipped {$stream->id}: " . $e->getMessage());
            }
        }

        $this->writeAppDefinitionSchema($path);

        return self::SUCCESS;
    }

    protected function resolvePath(?string $path): string
    {
        if ($path === null || $path === '') {
            return base_path();
        }

        if (str_starts_with($path, '/')) {
            return rtrim($path, '/');
        }

        return base_path($path);
    }

    protected function writeSchemaFile(Stream $stream, string $path)
    {
        $file = $stream->id . '.schema.json';
        $file = rtrim($path, '/') . '/' . $file;

        $schema = $stream->schema();

        $json = array_merge(
            $schema->tag()->toArray(),
            $schema->object()->toArray(),
        );

        $contents = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($file, $contents);

        $this->info($file);
    }

    /**
     * Output the app-definition schema: the root app.schema.json plus the
     * per-node $ref schemas. Sources the committed files in the package and
     * writes them under schemas/ in the target path.
     */
    protected function writeAppDefinitionSchema(string $path): void
    {
        $source = __DIR__ . '/../../../resources/schemas';

        $target = rtrim($path, '/') . '/schemas';

        File::ensureDirectoryExists($target . '/nodes');

        File::copy($source . '/app.schema.json', $target . '/app.schema.json');

        foreach (glob($source . '/nodes/*.schema.json') ?: [] as $file) {
            File::copy($file, $target . '/nodes/' . basename($file));
        }

        $this->info($target . '/app.schema.json (app-definition schema)');
    }
}
