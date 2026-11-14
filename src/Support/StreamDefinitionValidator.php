<?php

namespace Streams\Sdk\Support;

use Illuminate\Support\Arr;
use Opis\JsonSchema\Validator;
use Illuminate\Support\Facades\App;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Streams\Core\Support\Facades\Streams;

/**
 * Validates stream definitions (streams/{id}.json) against the
 * published JSON Schema, then against the running application
 * (registered field types, related streams, and a test build).
 */
class StreamDefinitionValidator
{
    public const SCHEMA_ID = 'https://streams.dev/schema/streams.schema.json';

    public static function schemaPath(): string
    {
        return dirname(__DIR__, 2) . '/resources/schemas/streams.schema.json';
    }

    public static function schema(): array
    {
        return json_decode(file_get_contents(static::schemaPath()), true);
    }

    /**
     * @param array|string $definition A decoded definition or its JSON.
     * @param string|null $id The stream ID (for example, from the file name).
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function validate(array|string $definition, ?string $id = null): array
    {
        $errors = [];
        $warnings = [];

        if (is_string($definition)) {

            $decoded = json_decode($definition);

            if (json_last_error() !== JSON_ERROR_NONE || !is_object($decoded)) {
                return $this->result(['Invalid JSON: ' . json_last_error_msg()], []);
            }

            $definition = json_decode($definition, true);
        }

        $errors = array_merge($errors, $this->schemaErrors($definition));

        [$semanticErrors, $warnings] = $this->semanticIssues($definition, $id);

        $errors = array_merge($errors, $semanticErrors);

        // Only try a real build when the definition looks sound,
        // so agents see the most specific errors first.
        if (!$errors) {
            $errors = array_merge($errors, $this->buildErrors($definition, $id));
        }

        return $this->result($errors, $warnings);
    }

    public function validateFile(string $path): array
    {
        if (!is_file($path)) {
            return $this->result(["File [{$path}] does not exist."], []);
        }

        return $this->validate((string) file_get_contents($path), basename($path, '.json'));
    }

    protected function schemaErrors(array $definition): array
    {
        $validator = new Validator();
        $validator->setMaxErrors(20);

        $data = json_decode(json_encode($definition ?: new \stdClass()));
        $schema = json_decode(file_get_contents(static::schemaPath()));

        $result = $validator->validate($data, $schema);

        if ($result->isValid()) {
            return [];
        }

        $errors = [];

        foreach ((new ErrorFormatter())->format($result->error()) as $path => $messages) {
            foreach ((array) $messages as $message) {
                $errors[] = ($path ?: '/') . ': ' . $message;
            }
        }

        return $errors;
    }

    protected function semanticIssues(array $definition, ?string $id = null): array
    {
        $errors = [];
        $warnings = [];

        if (isset($definition['handle']) && !isset($definition['id'])) {
            $warnings[] = '/handle: "handle" is deprecated. Use "id" or rely on the file name.';
        }

        $ownIds = array_filter([
            $id,
            Arr::get($definition, 'id'),
            Arr::get($definition, 'handle'),
        ], fn ($value) => is_string($value) && $value !== '');

        if (($extends = Arr::get($definition, 'extends')) && is_string($extends) && !Streams::exists($extends)) {
            $errors[] = "/extends: Stream [{$extends}] is not registered.";
        }

        $source = Arr::get($definition, 'config.source.type');

        if (is_string($source) && str_contains($source, '\\') && !class_exists($source)) {
            $errors[] = "/config/source/type: Adapter class [{$source}] does not exist.";
        }

        if (($model = Arr::get($definition, 'config.source.model')) && is_string($model) && !class_exists($model)) {
            $errors[] = "/config/source/model: Model class [{$model}] does not exist.";
        }

        foreach ($this->unresolvedImports($definition) as $pointer => $import) {
            $warnings[] = "{$pointer}: Import [{$import}] does not exist or is not valid JSON. streams/core resolves imports relative to the application base path.";
        }

        foreach ($this->fields($definition) as $pointer => $field) {

            $type = Arr::get($field, 'type', 'string');

            if (is_string($type) && !App::has('streams.core.field_type.' . $type)) {
                $errors[] = "{$pointer}/type: Field type [{$type}] is not registered. Registered types: "
                    . implode(', ', $this->fieldTypes()) . '.';
            }

            if (array_key_exists('default', $field)) {
                $warnings[] = "{$pointer}/default: \"default\" at the field root is ignored. Put it in config.default.";
            }

            if (isset($field['related']) && !Arr::has($field, 'config.related')) {
                $errors[] = "{$pointer}: \"related\" must be inside \"config\" (config.related). streams/core only reads config.related.";
            }

            foreach ($this->relatedIds(Arr::get($field, 'config.related')) as $related) {
                if (in_array($related, $ownIds, true) || Streams::exists($related)) {
                    continue;
                }

                $warnings[] = "{$pointer}/config/related: Stream [{$related}] is not registered yet.";
            }
        }

        return [$errors, $warnings];
    }

    protected function buildErrors(array $definition, ?string $id): array
    {
        if ($handle = Arr::pull($definition, 'handle')) {
            $definition['id'] = $definition['id'] ?? $handle;
        }

        $definition['id'] = $definition['id'] ?? $id ?? 'sdk_validation_' . md5(json_encode($definition));

        try {
            Streams::build($definition);
        } catch (\Throwable $e) {
            return ['Streams failed to build the definition: ' . $e->getMessage()];
        }

        return [];
    }

    /**
     * Normalize list and keyed field forms to
     * JSON pointer => field attributes.
     */
    protected function fields(array $definition): array
    {
        $fields = [];

        $definitionFields = $this->resolveImport(Arr::get($definition, 'fields', []));

        foreach ((array) $definitionFields as $key => $field) {

            $field = $this->resolveImport($field);

            $field = is_string($field) ? ['type' => $field] : $field;

            // An unresolved import is reported by unresolvedImports().
            if (is_string($field['type'] ?? null) && $this->isImport($field['type'])) {
                continue;
            }

            if (!is_array($field)) {
                continue;
            }

            $fields['/fields/' . $key] = $field;
        }

        return $fields;
    }

    /**
     * Imports ("@path/to/file.json") in "fields" that do not resolve
     * to a JSON file, keyed by JSON pointer.
     *
     * @return array<string, string>
     */
    protected function unresolvedImports(array $definition): array
    {
        $fields = Arr::get($definition, 'fields', []);

        if (is_string($fields)) {
            return $this->resolveImport($fields) === $fields && $this->isImport($fields)
                ? ['/fields' => $fields]
                : [];
        }

        $missing = [];

        foreach ((array) $fields as $key => $field) {
            if (is_string($field) && $this->isImport($field) && $this->resolveImport($field) === $field) {
                $missing['/fields/' . $key] = $field;
            }
        }

        return $missing;
    }

    protected function isImport(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, '@');
    }

    /**
     * Resolve an "@path/to/file.json" import the way streams/core's
     * StreamBuilder does (relative to the base path, JSON decoded).
     * Anything else, or an import that does not resolve, is returned as is.
     */
    protected function resolveImport(mixed $value): mixed
    {
        if (!$this->isImport($value)) {
            return $value;
        }

        $path = base_path(substr($value, 1));

        if (!is_file($path)) {
            return $value;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : $value;
    }

    /**
     * @return array<int, string>
     */
    protected function relatedIds(mixed $related): array
    {
        if (is_string($related)) {
            return [$related];
        }

        if (!is_array($related)) {
            return [];
        }

        return array_values(array_filter($related, fn ($id) => is_string($id) && $id !== ''));
    }

    protected function fieldTypes(): array
    {
        $types = array_keys((array) config('streams.core.field_types', []));

        sort($types);

        return $types;
    }

    protected function result(array $errors, array $warnings): array
    {
        return [
            'valid' => !$errors,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }
}
