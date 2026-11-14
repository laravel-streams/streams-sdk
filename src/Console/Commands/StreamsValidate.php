<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Support\StreamDefinitionValidator;
use Streams\Sdk\Support\StreamSourceValidator;

class StreamsValidate extends Command
{
    protected $signature = 'streams:validate
        {paths?* : Definition files to validate. Defaults to streams/*.json.}
        {--json : Output JSON for scripts and agents.}';

    protected $description = 'Validate stream definitions against the JSON Schema and the installed Core repository.';

    public function handle(StreamSourceValidator $validator, StreamDefinitionValidator $definition)
    {
        $paths = $this->argument('paths') ?: File::glob(base_path('streams/*.json'));

        $results = [];

        foreach ($paths as $path) {
            $absolute = str_starts_with($path, '/') ? $path : base_path($path);

            $results[ltrim(str_replace(base_path(), '', $absolute), '/')] = $this->validateFile($validator, $definition, $absolute);
        }

        $failed = array_filter($results, fn ($result) => ! $result['valid']);

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
        $this->line(count($results).' checked, '.count($failed).' invalid.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>, source_type: string|null}
     */
    protected function validateFile(StreamSourceValidator $validator, StreamDefinitionValidator $schemaValidator, string $path): array
    {
        if (! is_file($path)) {
            return $this->invalid('File ['.$path.'] does not exist.');
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return $this->invalid('Invalid JSON: '.json_last_error_msg());
        }

        $schema = $schemaValidator->validate($decoded, basename($path, '.json'));
        $source = $validator->validate($decoded);

        return [
            'valid' => $schema['valid'] && $source['valid'],
            'errors' => StreamDefinitionValidator::consolidate($source['errors'], $schema['errors']),
            'warnings' => StreamDefinitionValidator::consolidate($source['warnings'], $schema['warnings']),
            'source_type' => $source['source_type'],
        ];
    }

    /**
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>, source_type: string|null}
     */
    protected function invalid(string $error): array
    {
        return [
            'valid' => false,
            'errors' => [$error],
            'warnings' => [],
            'source_type' => null,
        ];
    }
}
