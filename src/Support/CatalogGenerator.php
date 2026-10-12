<?php

namespace Streams\Sdk\Support;

use ReflectionClass;
use ReflectionException;

/**
 * Generates the machine-readable Streams SDK catalog from code.
 *
 * The catalog is deterministic so CI can drift-test it: field types come
 * from the installed streams/core field type classes, UI builders come from
 * vendor/streams/ui when it is installed and otherwise from the bundled
 * manifest (resources/catalog/ui-builders.manifest.json).
 *
 * No timestamps or environment values are embedded.
 */
class CatalogGenerator
{
    /**
     * Per-type configuration knowledge, keyed by field type handle.
     *
     * This is the "options, defaults" half of the catalog. Everything the
     * field type itself reads from `config` at runtime that matters to an
     * IDE or agent goes here, including which keys are required.
     */
    protected array $fieldTypeMeta = [
        'select' => [
            'config' => [
                'options' => ['type' => 'object|string', 'required' => true, 'description' => 'The selectable options keyed by value.'],
            ],
            'defaults' => ['options' => []],
        ],
        'multiselect' => [
            'config' => [
                'options' => ['type' => 'object|string', 'required' => true, 'description' => 'The selectable options keyed by value.'],
            ],
            'defaults' => ['options' => []],
        ],
        'relationship' => [
            'config' => [
                'related' => ['type' => 'string|array', 'required' => true, 'description' => 'One stream id, or a list of ids for polymorphic and multi-target fields.'],
            ],
            'defaults' => ['related' => null],
        ],
        'object' => [
            'config' => [
                'allowed' => ['type' => 'object', 'required' => false, 'description' => 'Nested objects: {"stream": ...}, {"generic": ...} or {"prototype": ...}.'],
                'properties' => ['type' => 'object', 'required' => false, 'description' => 'Inline nested field definitions.'],
            ],
            'defaults' => [],
        ],
        'uuid' => [
            'config' => [
                'default' => ['type' => 'boolean|string|integer', 'required' => false, 'description' => '"default": true generates a UUID when an entry is created.'],
            ],
            'defaults' => ['default' => false],
        ],
    ];

    /**
     * Full catalog payload.
     */
    public function catalog(): array
    {
        $fieldTypes = $this->fieldTypes();
        $builders = $this->uiBuilders();

        return [
            '$schema' => 'https://streams.dev/schema/catalog.schema.json',
            'version' => 1,
            'source' => [
                'core' => $this->coreTypesPath(),
                'ui' => $this->uiSource(),
            ],
            'field_types' => $fieldTypes,
            'ui' => [
                'builders' => $builders,
            ],
        ];
    }

    /**
     * JSON Schema (draft-07) for the catalog file itself.
     */
    public function catalogSchema(): array
    {
        $fieldType = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['handle', 'class'],
            'properties' => [
                'handle' => ['type' => 'string', 'description' => 'The field type handle, kebab-case.'],
                'class' => ['type' => 'string', 'description' => 'The field type class.'],
                'schema' => ['type' => 'string', 'description' => 'The field JSON schema class.'],
                'decorator' => ['type' => 'string', 'description' => 'The field decorator class.'],
                'config' => ['type' => 'object', 'description' => 'Config keys the type reads, with required flags.'],
                'defaults' => ['type' => 'object', 'description' => 'Default config values.'],
                'allowed_parents' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];

        $builder = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['id', 'group'],
            'properties' => [
                'id' => ['type' => 'string'],
                'group' => ['type' => 'string'],
                'label' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'options' => ['type' => 'object'],
                'allowed_parents' => ['type' => 'array', 'items' => ['type' => 'string']],
                'source' => ['type' => 'string', 'enum' => ['code', 'manifest']],
            ],
        ];

        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            '$id' => 'https://streams.dev/schema/catalog.schema.json',
            'title' => 'Streams SDK catalog',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['version', 'source', 'field_types', 'ui'],
            'properties' => [
                '$schema' => ['type' => 'string', 'description' => 'Points at this schema.'],
                'version' => ['type' => 'integer'],
                'source' => [
                    'type' => 'object',
                    'required' => ['core', 'ui'],
                    'properties' => [
                        'core' => ['type' => 'string'],
                        'ui' => ['type' => 'string'],
                    ],
                ],
                'field_types' => ['type' => 'array', 'items' => $fieldType],
                'ui' => [
                    'type' => 'object',
                    'required' => ['builders'],
                    'properties' => ['builders' => ['type' => 'array', 'items' => $builder]],
                ],
            ],
        ];
    }

    /**
     * The stream definition schema (streams.schema.json) for IDE and agent
     * validation. The field type enum is generated from code.
     */
    public function streamDefinitionSchema(): array
    {
        $types = array_column($this->fieldTypes(), 'handle');

        $fieldConfig = [];

        foreach ($this->fieldTypes() as $type) {
            $config = $type['config'] ?? [];

            if (!$config instanceof \stdClass && $config !== []) {
                foreach ($config as $key => $rule) {
                    $entry = ['oneOf' => $this->configTypes($rule['type'] ?? 'string')];

                    if (($rule['description'] ?? null) !== null) {
                        $entry['description'] = $rule['description'];
                    }

                    if ($rule['required'] ?? false) {
                        $entry['description'] = trim(
                            (($rule['description'] ?? '') . ' Required when the field type is used.')
                        );

                        if ($entry['description'] === '') {
                            $entry['description'] = 'Required when the field type is used.';
                        }
                    }

                    $fieldConfig[$key] ??= $entry;
                }
            }
        }

        $fieldDefinition = [
            'type' => 'object',
            'additionalProperties' => true,
            'properties' => [
                'handle' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]*$'],
                'type' => ['type' => 'string', 'enum' => $types],
                'name' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'required' => ['type' => 'boolean'],
                'unique' => ['type' => 'boolean'],
                'rules' => ['type' => 'array', 'items' => ['type' => 'string']],
                'config' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'properties' => $fieldConfig,
                ],
            ],
        ];

        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            '$id' => 'https://streams.dev/schema/streams.schema.json',
            'title' => 'Stream definition',
            'additionalProperties' => true,
            'required' => ['name', 'fields'],
            'properties' => [
                'id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'handle' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'extends' => ['type' => 'string'],
                'config' => ['type' => 'object'],
                'fields' => [
                    'type' => ['array', 'object'],
                    'items' => $fieldDefinition,
                    'additionalProperties' => $fieldDefinition,
                ],
                'rules' => ['type' => 'object'],
                'route' => ['type' => 'object'],
                'routes' => ['type' => 'object'],
                'data' => ['type' => 'object'],
                'ui' => ['type' => 'object'],
            ],
        ];
    }

    protected function configTypes(string $type): array
    {
        $map = [
            'string' => ['type' => 'string'],
            'boolean' => ['type' => 'boolean'],
            'object' => ['type' => 'object', 'additionalProperties' => true],
            'string|array' => [
                ['type' => 'string'],
                ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'object|string' => [
                ['type' => 'object', 'additionalProperties' => true],
                ['type' => 'string'],
            ],
            'boolean|string|integer' => [
                ['type' => 'boolean'],
                ['type' => 'string'],
                ['type' => 'integer'],
            ],
            'array' => ['type' => 'array'],
        ];

        $schemas = $map[$type] ?? [['type' => 'string']];

        // oneOf always takes a list of schemas; a single schema is wrapped.
        if (array_key_exists('type', $schemas)) {
            $schemas = [$schemas];
        }

        return $schemas;
    }

    /**
     * Empty payloads encode as JSON objects ({}), never arrays ([]).
     */
    protected function asObject(array $value): object|array
    {
        return empty($value) ? new \stdClass() : $value;
    }

    /**
     * Catalog the field types shipped by streams/core.
     */
    public function fieldTypes(): array
    {
        $path = $this->coreTypesPath();

        if (!is_dir($path)) {
            return [];
        }

        $types = [];

        foreach (glob($path . '/*FieldType.php') ?: [] as $file) {
            $short = substr(basename($file), 0, -4);

            $class = 'Streams\\Core\\Field\\Types\\' . $short;

            if (!class_exists($class)) {
                continue;
            }

            $handle = $this->handleFromClass($short);

            $types[] = array_filter([
                'handle' => $handle,
                'class' => $class,
                'schema' => $this->className($class, 'getSchemaName'),
                'decorator' => $this->className($class, 'getDecoratorName'),
                'config' => $this->asObject($this->fieldTypeMeta[$handle]['config'] ?? []),
                'defaults' => $this->asObject($this->fieldTypeMeta[$handle]['defaults'] ?? []),
                'allowed_parents' => $this->allowedParents($handle),
            ], fn ($value) => $value !== null);
        }

        usort($types, fn ($a, $b) => strcmp($a['handle'], $b['handle']));

        return array_values($types);
    }

    /**
     * The UI builders. Read from vendor/streams/ui when installed, otherwise
     * from the bundled manifest so the catalog stays complete.
     */
    public function uiBuilders(): array
    {
        $path = $this->uiInstallPath();

        if (is_dir($path)) {
            return $this->uiBuildersFromCode($path);
        }

        return $this->uiBuildersFromManifest();
    }

    /**
     * The containers a field type may live in.
     */
    protected function allowedParents(string $handle): array
    {
        $parents = ['stream'];

        if ($handle === 'object') {
            $parents[] = 'object';
        }

        return $parents;
    }

    /**
     * kebab-case handle from a class short name, e.g. StringFieldType -> string.
     */
    protected function handleFromClass(string $short): string
    {
        $name = preg_replace('/FieldType$/', '', $short) ?? $short;

        $name = preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name;

        return strtolower(str_replace('_', '-', $name));
    }

    /**
     * Return a getter method's result (schema/decorator class) when defined.
     */
    protected function className(string $class, string $method): ?string
    {
        try {
            $reflection = new ReflectionClass($class);

            if (!$reflection->hasMethod($method)) {
                return null;
            }

            $value = $reflection->newInstanceWithoutConstructor()->{$method}();

            if ($value === null) {
                return null;
            }

            // Do not class_exists() here: core dev snapshots can reference
            // schema classes that do not autoload cleanly.
            return is_string($value) && str_contains($value, '\\') ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Directory of the installed streams/core field types, relative to the
     * package so generated output is identical on every machine.
     */
    protected function coreTypesPath(): string
    {
        $reflection = new ReflectionClass(\Streams\Core\StreamsServiceProvider::class);

        return $this->relativeToPackage(
            dirname($reflection->getFileName()) . '/Field/Types'
        );
    }

    /**
     * Make a path relative to the SDK package root, keeping generated output
     * stable across machines.
     */
    protected function relativeToPackage(string $path): string
    {
        $root = rtrim(dirname(__DIR__, 2), '/');

        $path = rtrim($path, '/');

        if (str_starts_with($path, $root . '/')) {
            return substr($path, strlen($root) + 1);
        }

        return $path;
    }

    /**
     * Directory of an installed streams/ui package, or an empty string.
     */
    protected function uiInstallPath(): string
    {
        if (class_exists(\Streams\Ui\UiServiceProvider::class)) {
            $reflection = new ReflectionClass(\Streams\Ui\UiServiceProvider::class);

            return dirname($reflection->getFileName(), 2);
        }

        return '';
    }

    /**
     * Human label for the UI builder source.
     */
    protected function uiSource(): string
    {
        $path = $this->uiInstallPath();

        if (is_dir($path)) {
            return $this->relativeToPackage($path);
        }

        return 'resources/catalog/ui-builders.manifest.json (streams/ui is not installed)';
    }

    /**
     * Ship the bundled manifest until streams/ui is installed.
     */
    protected function uiBuildersFromManifest(): array
    {
        $path = __DIR__ . '/../../resources/catalog/ui-builders.manifest.json';

        if (!file_exists($path)) {
            return [];
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        foreach ($manifest as &$builder) {
            $builder['source'] = 'manifest';

            // Empty options must stay JSON objects ({}) once re-encoded.
            if (empty($builder['options']) || !is_array($builder['options'])) {
                $builder['options'] = new \stdClass();
            }
        }

        return $manifest;
    }

    /**
     * Scan streams/ui builder classes.
     *
     * @return array<array<string, mixed>>
     */
    protected function uiBuildersFromCode(string $path): array
    {
        $builders = [];

        foreach (glob($path . '/src/*/*.php') ?: [] as $file) {
            // Builders in streams/ui currently mirror the manifest ids by
            // class short name; keep the manifest table authoritative for
            // options until the package exports them.
            $builders[] = [
                'id' => strtolower(substr(basename($file), 0, -4)),
                'source' => 'code',
            ];
        }

        return $builders;
    }
}