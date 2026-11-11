<?php

namespace Streams\Sdk\Console\Onboard;

/**
 * Draft-07 checks for streams.onboard.json without a schema library.
 * Unknown keys are errors. Partial arrays (TUI / flags) may omit keys;
 * documents read from disk must include every required key.
 */
final class RecipeValidator
{
    /** @var list<string> */
    public const KEYS = [
        'version',
        'surface',
        'frontend',
        'panels',
        'multi_tenancy',
        'api',
        'discover_streams_api',
        'streams_api_base_url',
        'sample_domain',
        'created_at',
    ];

    public static function schemaPath(): string
    {
        return dirname(__DIR__, 3).'/resources/schemas/onboard.schema.json';
    }

    public static function presetPath(string $name): ?string
    {
        $safe = preg_replace('/[^a-z0-9_-]/i', '', $name) ?? '';
        if ($safe === '' || $safe !== $name) {
            return null;
        }

        $path = dirname(__DIR__, 3).'/resources/recipes/'.$safe.'.json';

        return is_file($path) ? $path : null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function assertKnown(array $input): void
    {
        $allowed = array_merge(self::KEYS, ['$schema']);
        $unknown = array_diff(array_keys($input), $allowed);

        if ($unknown !== []) {
            throw new \InvalidArgumentException(
                'Unknown onboard recipe keys: '.implode(', ', $unknown)
            );
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function assertDocument(array $input): void
    {
        self::assertKnown($input);

        foreach (self::KEYS as $key) {
            if (! array_key_exists($key, $input)) {
                throw new \InvalidArgumentException("Onboard recipe is missing [{$key}].");
            }
        }

        if ($input['version'] !== 1 && $input['version'] !== '1') {
            throw new \InvalidArgumentException('Onboard recipe version must be 1.');
        }

        if (! in_array($input['surface'], ['laravel', 'js'], true)) {
            throw new \InvalidArgumentException('Onboard recipe surface must be laravel or js.');
        }

        foreach (['frontend', 'multi_tenancy', 'api', 'discover_streams_api'] as $bool) {
            if (! is_bool($input[$bool])) {
                throw new \InvalidArgumentException("Onboard recipe [{$bool}] must be a boolean.");
            }
        }

        if (! is_int($input['panels']) || $input['panels'] < 0) {
            throw new \InvalidArgumentException('Onboard recipe panels must be an integer >= 0.');
        }

        if ($input['streams_api_base_url'] !== null && ! is_string($input['streams_api_base_url'])) {
            throw new \InvalidArgumentException('Onboard recipe streams_api_base_url must be a string or null.');
        }

        if ($input['sample_domain'] !== null && ! is_string($input['sample_domain'])) {
            throw new \InvalidArgumentException('Onboard recipe sample_domain must be a string or null.');
        }

        if (! is_string($input['created_at']) || $input['created_at'] === '') {
            throw new \InvalidArgumentException('Onboard recipe created_at must be a string.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function readDocument(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException("Invalid JSON in [{$path}].");
        }

        self::assertDocument($decoded);

        return $decoded;
    }
}
