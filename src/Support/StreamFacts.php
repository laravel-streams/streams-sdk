<?php

namespace Streams\Sdk\Support;

use Illuminate\Support\Facades\File;
use Streams\Core\Field\Field;
use Streams\Core\Stream\Stream;
use Streams\Core\Support\Facades\Streams;
use Throwable;

/**
 * The read model for one stream: the same facts in the same shape for the MCP
 * tools, the streams:tui menu, and `--json` command output. Nothing here
 * renders anything, so every surface agrees.
 *
 * A stream is a description of data: fields, rules, and a source. The source
 * (flat files, a database table, an Eloquent model, a collection) is
 * configuration, so an entry's shape does not change when storage does. That
 * is why this class reports the source and the fields together.
 */
class StreamFacts
{
    /**
     * Short rows for a list: enough to pick a stream, not to model one.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function summaries(?int $limit = null): array
    {
        $streams = Streams::collection()->sortBy(fn (Stream $stream) => (string) $stream->id);

        if ($limit !== null) {
            $streams = $streams->take($limit);
        }

        return $streams
            ->map(fn (Stream $stream) => static::summary($stream))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Stream|string $stream): array
    {
        $stream = static::resolve($stream);

        return array_filter([
            'id' => (string) $stream->id,
            'name' => (string) ($stream->name ?: $stream->name()),
            'description' => (string) ($stream->description ?: ''),
            'source' => static::sourceLabel($stream),
            'key' => (string) $stream->config('key_name', 'id'),
            'fields' => $stream->fields->pluck('handle')->all(),
            'definition' => static::definitionPath($stream),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * Everything an agent or a person needs to work with one stream safely:
     * source, key, routes, fields with their resolved rules, and where the
     * definition lives. Read this before writing entries or editing a field.
     *
     * @return array<string, mixed>
     */
    public static function describe(Stream|string $stream, bool $withRules = true): array
    {
        $stream = static::resolve($stream);

        return array_filter([
            'id' => (string) $stream->id,
            'name' => (string) ($stream->name ?: $stream->name()),
            'description' => (string) ($stream->description ?: ''),
            'namespace' => (string) ($stream->namespace ?: ''),
            'key' => (string) $stream->config('key_name', 'id'),
            'source' => static::source($stream),
            'routes' => static::routes($stream),
            'fields' => static::fields($stream, $withRules),
            'rules' => $withRules ? static::rules($stream) : [],
            'entry_count' => static::count($stream),
            'definition' => static::definitionPath($stream),
            'original_definition' => static::originalDefinition($stream),
            'next' => [
                'Read entries with `php artisan entries:list '.$stream->id.'` or the list-entries tool.',
                'Write or edit the definition file, then run `php artisan streams:validate --json`.',
                'Regenerate entry types with `php artisan streams:schema --include='.$stream->id.'`.',
            ],
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * Field handles, types, and the rules Core will apply. This is the typed
     * contract an agent should code against instead of inferring from data.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function fields(Stream $stream, bool $withRules = true): array
    {
        $fields = [];

        foreach ($stream->fields as $field) {
            if (! $field instanceof Field) {
                continue;
            }

            $fields[(string) $field->handle] = array_filter([
                'type' => (string) $field->type,
                'required' => (bool) $field->isRequired(),
                'unique' => $field->hasRule('unique'),
                'protected' => (bool) $field->protected,
                'rules' => $withRules ? static::fieldRules($field) : [],
                'description' => (string) ($field->description ?: ''),
                'config' => static::fieldConfig($field),
            ], fn ($value) => $value !== null && $value !== '' && $value !== [] && $value !== false);
        }

        return $fields;
    }

    /**
     * @return array<int, string>
     */
    public static function fieldRules(Field $field): array
    {
        return array_map(
            fn ($rule) => is_string($rule) ? $rule : (is_object($rule) ? get_class($rule) : (string) $rule),
            array_values((array) $field->rules()),
        );
    }

    /**
     * Config worth reading, minus the noise. Full config is in the definition
     * file, which is where a config value is edited.
     *
     * @return array<string, mixed>
     */
    public static function fieldConfig(Field $field): array
    {
        $interesting = ['related', 'prefix', 'default', 'options', 'items', 'wrapper', 'mime_types', 'folder'];

        return array_intersect_key((array) $field->config, array_flip($interesting));
    }

    /**
     * The stream's own rules (keyed by field handle), as Core will apply them.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(Stream $stream): array
    {
        try {
            $rules = [];

            foreach ($stream->rules() as $handle => $ruleSet) {
                $rules[(string) $handle] = array_map(
                    fn ($rule) => is_string($rule) ? $rule : (is_object($rule) ? get_class($rule) : (string) $rule),
                    (array) $ruleSet,
                );
            }

            return $rules;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Source as Core resolved it: the adapter name plus its shape (path, table,
     * or model). An omitted type means the app default source.
     *
     * @return array<string, mixed>
     */
    public static function source(Stream $stream): array
    {
        $source = (array) $stream->config('source', []);

        return array_filter([
            'type' => (string) ($source['type'] ?? config('streams.core.default_source', 'filebase')),
            'format' => (string) ($source['format'] ?? ''),
            'path' => (string) ($source['path'] ?? ''),
            'table' => (string) ($source['table'] ?? ''),
            'model' => (string) ($source['model'] ?? ''),
            'adapter' => (string) ($source['adapter'] ?? ''),
        ], fn ($value) => $value !== '');
    }

    public static function sourceLabel(Stream $stream): string
    {
        $source = static::source($stream);

        $detail = ($source['path'] ?? '') ?: ($source['table'] ?? '') ?: ($source['model'] ?? '') ?: '';

        $label = $source['type'] ?? 'filebase';

        if (isset($source['format']) && $source['format'] !== '') {
            $label .= ' ('.$source['format'].')';
        }

        return $detail !== '' ? $label.' — '.$detail : $label;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function routes(Stream $stream): array
    {
        return collect($stream->routes ?: [])
            ->map(fn ($route) => is_array($route) ? array_filter($route, fn ($value) => $value !== null && $value !== '' && $value !== []) : ['route' => (string) $route])
            ->values()
            ->all();
    }

    /**
     * Entry count, or null when the source cannot answer (an unreadable path,
     * a missing table). Never throw for a fact the menu can live without.
     */
    public static function count(Stream $stream): ?int
    {
        try {
            return (int) $stream->entries()->count();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The definition as it sits on disk, or null when the stream was
     * registered in PHP (an addon default, or Streams::register at runtime).
     */
    public static function definitionPath(Stream $stream): ?string
    {
        $id = (string) $stream->id;

        foreach (static::definitionCandidates($id) as $path) {
            if (is_file($path)) {
                return static::relative($path);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function originalDefinition(Stream $stream): ?array
    {
        $path = static::definitionPath($stream);

        if ($path === null) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents(base_path($path)), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Attributes of one entry, with protected fields held back. Use this for
     * every entry the SDK shows or returns so a secret cannot ride along.
     *
     * @return array<string, mixed>
     */
    public static function attributes(Stream $stream, mixed $entry): array
    {
        $attributes = is_object($entry) && method_exists($entry, 'getAttributes')
            ? $entry->getAttributes()
            : (array) $entry;

        $protected = $stream->fields
            ->filter(fn ($field) => $field instanceof Field && $field->protected)
            ->pluck('handle')
            ->all();

        return array_diff_key($attributes, array_flip(array_map('strval', $protected)));
    }

    /**
     * The one directory definitions live in, absolute. Configurable so tests
     * and unusual layouts can point elsewhere; Core loads this same path.
     */
    public static function definitionsDirectory(): string
    {
        $path = (string) config('streams.sdk.definitions.path', 'streams');

        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    /**
     * @return array<int, string>
     */
    public static function definitionCandidates(string $id): array
    {
        $directory = static::definitionsDirectory();

        $candidates = [
            $directory.'/'.$id.'.json',
            $directory.'/'.$id.'/'.$id.'.json',
        ];

        foreach (glob(base_path('vendor/streams/*/streams/'.$id.'.json')) ?: [] as $path) {
            $candidates[] = $path;
        }

        foreach (glob(base_path('addons/*/*/streams/'.$id.'.json')) ?: [] as $path) {
            $candidates[] = $path;
        }

        return $candidates;
    }

    public static function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/');
    }

    public static function resolve(Stream|string $stream): Stream
    {
        return $stream instanceof Stream ? $stream : Streams::make($stream);
    }

    /**
     * True when the file list in streams/ and the registered streams agree,
     * and where they do not. A definition that never registered is the most
     * common beginner wall, so both menus lead with it.
     *
     * @return array{files: int, registered: int, unregistered: array<int, string>, missing_files: array<int, string>}
     */
    public static function coverage(): array
    {
        $registered = Streams::collection()->keys()->map(fn ($id) => (string) $id)->all();

        $files = [];

        foreach (File::glob(static::definitionsDirectory().'/*.json') as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);

            $files[] = is_array($decoded) && isset($decoded['id'])
                ? (string) $decoded['id']
                : basename((string) $file, '.json');
        }

        return [
            'files' => count($files),
            'registered' => count($registered),
            'unregistered' => array_values(array_diff($files, $registered)),
            'missing_files' => array_values(array_diff($registered, $files)),
        ];
    }
}
