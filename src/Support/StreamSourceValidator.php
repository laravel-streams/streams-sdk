<?php

namespace Streams\Sdk\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionClass;
use Streams\Core\Repository\Repository;

/**
 * Checks stream source configuration against the installed Core repository.
 *
 * Core reads config.source (type, format, path, table, model, adapter).
 * An omitted type uses streams.core.default_source. A stream-level handle
 * is only a deprecated alias of id. The validation key, config.pagination,
 * and config.sort are not applied.
 */
class StreamSourceValidator
{
    /**
     * @param  array<string, mixed>  $definition
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>, source_type: string|null}
     */
    public function validate(array $definition): array
    {
        $errors = [];
        $warnings = [];

        if (array_key_exists('handle', $definition) && ! array_key_exists('id', $definition)) {
            $warnings[] = '/handle: "handle" is a deprecated alias of "id" and is not a required stream property. Use "id" or the file name.';
        }

        if (array_key_exists('validation', $definition)) {
            $errors[] = '/validation: "validation" is not applied. Use field rules, required, or unique, or stream rules keyed by field handle.';
        }

        $config = $definition['config'] ?? [];

        if (! is_array($config)) {
            $errors[] = '/config: "config" must be an object.';
            $config = [];
        }

        if (array_key_exists('pagination', $config)) {
            $errors[] = '/config/pagination: "config.pagination" is not applied. Core paginates with Criteria::paginate(), not stream configuration.';
        }

        if (array_key_exists('sort', $config)) {
            $errors[] = '/config/sort: "config.sort" is not applied. Core sorts with Criteria::orderBy(), not stream configuration.';
        }

        $sourceType = $this->checkSource($config, $errors);

        $sourcePath = $config['source']['path'] ?? null;

        if (is_string($sourcePath) && (str_starts_with($sourcePath, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $sourcePath))) {
            $warnings[] = '/config/source/path: core strips the leading separator and joins this onto the app root (base_path()), so an absolute path writes into a mirrored tree inside the project. Use a path relative to the project root, for example "storage/app/data/posts".';
        }

        return [
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'source_type' => $sourceType,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $errors
     */
    protected function checkSource(array $config, array &$errors): ?string
    {
        if (! array_key_exists('source', $config)) {
            return $this->defaultSourceType($errors);
        }

        $source = $config['source'];

        if (! is_array($source)) {
            $errors[] = '/config/source: "config.source" must be an object.';

            return null;
        }

        if (array_key_exists('adapter', $source)) {
            $adapter = $source['adapter'];

            if (! is_string($adapter) || $adapter === '' || ! class_exists($adapter)) {
                $shown = is_string($adapter) && $adapter !== '' ? $adapter : gettype($adapter);
                $errors[] = '/config/source/adapter: Adapter class ['.$shown.'] does not exist.';
            }

            return null;
        }

        if (! array_key_exists('type', $source) || $source['type'] === null) {
            $type = $this->defaultSourceType($errors);
        } elseif (! is_string($source['type']) || $source['type'] === '') {
            $errors[] = '/config/source/type: Source type must be a string.';

            return null;
        } elseif (str_contains($source['type'], '\\')) {
            $errors[] = '/config/source/type: ['.$source['type'].'] is a class name. Put adapter classes in config.source.adapter. Source type must be a Repository adapter name.';

            return null;
        } elseif (! in_array($source['type'], $this->adapterTypes(), true)) {
            $errors[] = '/config/source/type: Source type ['.$source['type'].'] is not a Repository adapter. Known types: '.implode(', ', $this->adapterTypes()).'.';

            return null;
        } else {
            $type = $source['type'];
        }

        if (! is_string($type)) {
            return null;
        }

        $this->checkSourceShape($type, $source, $errors);

        return $type;
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function defaultSourceType(array &$errors): ?string
    {
        $type = Config::get('streams.core.default_source', 'filebase');

        if (! is_string($type) || ! in_array($type, $this->adapterTypes(), true)) {
            $shown = is_string($type) ? $type : gettype($type);
            $errors[] = '/config/source/type: Default source ['.$shown.'] is not a Repository adapter.';

            return null;
        }

        return $type;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, string>  $errors
     */
    protected function checkSourceShape(string $type, array $source, array &$errors): void
    {
        if ($type === 'eloquent') {
            $model = $source['model'] ?? null;

            if (! is_string($model) || $model === '' || ! class_exists($model)) {
                $shown = is_string($model) && $model !== '' ? $model : 'missing';
                $errors[] = '/config/source/model: eloquent sources require config.source.model to be a class that exists. ['.$shown.'] is not.';
            }
        }

        if (array_key_exists('format', $source)) {
            $this->checkFormat($type, $source['format'], $errors);
        }

        foreach (['path', 'table', 'file', 'connection', 'disk'] as $key) {
            if (array_key_exists($key, $source) && ! is_string($source[$key])) {
                $errors[] = '/config/source/'.$key.': "'.$key.'" must be a string.';
            }
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function checkFormat(string $type, mixed $format, array &$errors): void
    {
        if (! is_string($format) || $format === '') {
            $errors[] = '/config/source/format: "format" must be a string.';

            return;
        }

        $known = $this->formatsFor($type);

        if ($known === null || in_array($format, $known, true)) {
            return;
        }

        $errors[] = '/config/source/format: Format ['.$format.'] is not registered for source type ['.$type.']. Known formats: '.implode(', ', $known).'.';
    }

    /**
     * Formats the installed adapter actually branches on.
     * Null means this source type does not read format.
     *
     * @return array<int, string>|null
     */
    protected function formatsFor(string $type): ?array
    {
        if ($type === 'filebase') {
            $formats = array_keys((array) Config::get('streams.core.sources.filebase.formats', []));
            sort($formats);

            return $formats;
        }

        if ($type === 'file') {
            return ['csv', 'json'];
        }

        return null;
    }

    /**
     * Adapter names Repository can construct (new{Type}Adapter).
     *
     * @return array<int, string>
     */
    protected function adapterTypes(): array
    {
        $types = [];

        foreach ((new ReflectionClass(Repository::class))->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== Repository::class) {
                continue;
            }

            if (! preg_match('/^new(.+)Adapter$/', $method->getName(), $matches)) {
                continue;
            }

            $types[] = Str::snake($matches[1]);
        }

        $types = array_values(array_unique($types));
        sort($types);

        return $types;
    }
}
