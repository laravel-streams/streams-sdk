<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Support\StreamDefinitionValidator;

class StreamsValidate extends Command
{
    protected $signature = 'streams:validate
        {paths?* : Definition files to validate. Defaults to streams/*.json.}
        {--json : Output JSON for scripts and agents.}';

    protected $description = 'Validate stream definitions against the Streams JSON Schema and the running app.';

    public function handle(StreamDefinitionValidator $validator)
    {
        $paths = $this->argument('paths') ?: File::glob(base_path('streams/*.json'));

        $results = [];

        foreach ($paths as $path) {

            $absolute = str_starts_with($path, '/') ? $path : base_path($path);

            $results[ltrim(str_replace(base_path(), '', $absolute), '/')] = $validator->validateFile($absolute);
        }

        $failed = array_filter($results, fn ($result) => !$result['valid']);

        if ($this->option('json')) {

            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        foreach ($results as $file => $result) {

            $result['valid']
                ? $this->info("✓ {$file}")
                : $this->error("✗ {$file}");

            foreach ($result['errors'] as $error) {
                $this->line("    error: {$error}");
            }

            foreach ($result['warnings'] as $warning) {
                $this->line("    warning: {$warning}");
            }
        }

        $this->newLine();
        $this->line(count($results) . ' checked, ' . count($failed) . ' invalid.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
