<?php

namespace Streams\Sdk\Tests\Support;

use Streams\Sdk\Tests\SdkTestCase;
use Streams\Sdk\Support\CatalogGenerator;

/**
 * Catalog drift guard.
 *
 * Every builder (manifest) or field type change must be reflected in the
 * committed catalog, else agents and IDEs validate stale data. Regenerate,
 * compare against the committed files, and fail when they differ.
 */
class CatalogDriftTest extends SdkTestCase
{
    protected string $resources = __DIR__ . '/../../resources';

    protected function encode(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . PHP_EOL;
    }

    public function test_catalog_is_up_to_date_with_field_types_and_builders(): void
    {
        $generator = new CatalogGenerator();

        $expected = [
            "$this->resources/catalog/catalog.json" => $this->encode($generator->catalog()),
            "$this->resources/catalog/catalog.schema.json" => $this->encode($generator->catalogSchema()),
            "$this->resources/schemas/streams.schema.json" => $this->encode($generator->streamDefinitionSchema()),
        ];

        foreach ($expected as $file => $contents) {
            $this->assertFileExists($file);

            $this->assertSame(
                $contents,
                file_get_contents($file),
                "Catalog output is stale. Run `php artisan streams:catalog` and commit the regenerated files. ({$file})"
            );
        }
    }

    public function test_catalog_contents_are_sane(): void
    {
        $catalog = json_decode((string) file_get_contents("$this->resources/catalog/catalog.json"), true);

        $this->assertNotEmpty($catalog['field_types']);
        $this->assertNotEmpty($catalog['ui']['builders']);

        foreach ($catalog['field_types'] as $type) {
            $this->assertMatchesRegularExpression('/^[a-z-]+$/', $type['handle']);
            $this->assertStringStartsWith('Streams\\Core\\Field\\Types\\', $type['class']);
            $this->assertContains('stream', $type['allowed_parents']);
        }

        foreach ($catalog['ui']['builders'] as $builder) {
            $this->assertArrayHasKey('id', $builder);
            $this->assertArrayHasKey('group', $builder);
            $this->assertArrayHasKey('allowed_parents', $builder);
        }
    }

    public function test_stream_schema_offers_code_derived_field_types(): void
    {
        $schema = json_decode((string) file_get_contents("$this->resources/schemas/streams.schema.json"), true);

        $types = array_column(
            json_decode((string) file_get_contents("$this->resources/catalog/catalog.json"), true)['field_types'],
            'handle'
        );

        $this->assertSame($types, $schema['properties']['fields']['items']['properties']['type']['enum']);
    }

    public function test_generated_schemas_validate(): void
    {
        $catalog = json_decode((string) file_get_contents("$this->resources/catalog/catalog.json"));
        $catalogSchema = json_decode((string) file_get_contents("$this->resources/catalog/catalog.schema.json"));
        $streamsSchema = json_decode((string) file_get_contents("$this->resources/schemas/streams.schema.json"));

        // The catalog must validate against its own schema.
        $this->assertSchemaValid($catalog, $catalogSchema, '$');

        // oneOf is always a list (regression: single-type config keys once
        // emitted a bare schema object, which is invalid JSON Schema).
        $this->assertOneOfIsAList($streamsSchema);

        // A representative definition validates against streams.schema.json.
        $definition = json_decode(json_encode([
            'name' => 'Blog Posts',
            'fields' => [
                ['handle' => 'id', 'type' => 'uuid', 'config' => ['default' => true]],
                ['handle' => 'author', 'type' => 'relationship', 'config' => ['related' => 'users']],
                ['handle' => 'category', 'type' => 'select', 'config' => ['options' => (object) ['draft' => 'Draft', 'live' => 'Live']]],
                ['handle' => 'meta', 'type' => 'object', 'config' => ['allowed' => (object) [], 'properties' => (object) []]],
            ],
        ]));

        $this->assertSchemaValid($definition, $streamsSchema, '$');
    }

    /**
     * Minimal JSON Schema (draft-07) validator for the keyword subset the
     * generated schemas use.
     */
    protected function assertSchemaValid(mixed $data, \stdClass $schema, string $path): void
    {
        if (isset($schema->enum)) {
            $this->assertContains($data, $schema->enum, "{$path}: not one of the enum values.");
        }

        if (isset($schema->type)) {
            $types = is_array($schema->type) ? $schema->type : [$schema->type];

            $actual = match (true) {
                is_bool($data) => 'boolean',
                is_int($data) => 'integer',
                is_float($data) => 'number',
                is_string($data) => 'string',
                is_array($data) => 'array',
                $data instanceof \stdClass => 'object',
                $data === null => 'null',
            };

            $this->assertContains($actual, $types, "{$path}: expected type(s) " . implode(', ', $types) . ", got {$actual}.");
        }

        if (isset($schema->pattern)) {
            $this->assertMatchesRegularExpression('/' . str_replace('/', '\/', $schema->pattern) . '/', (string) $data, "{$path}: pattern mismatch.");
        }

        if ($data instanceof \stdClass) {
            if (isset($schema->required)) {
                foreach ($schema->required as $key) {
                    $this->assertTrue(property_exists($data, $key), "{$path}: missing required property [{$key}].");
                }
            }

            foreach ($schema->properties ?? [] as $key => $propertySchema) {
                if (property_exists($data, $key)) {
                    $this->assertSchemaValid($data->{$key}, $propertySchema, "{$path}.{$key}");
                }
            }

            if (isset($schema->additionalProperties) && $schema->additionalProperties === false) {
                foreach (get_object_vars($data) as $key => $value) {
                    $this->assertTrue(isset($schema->properties->{$key}), "{$path}: unexpected property [{$key}].");
                }
            }

            if (isset($schema->oneOf)) {
                foreach ($schema->oneOf as $index => $option) {
                    try {
                        $this->assertSchemaValid($data, $option, "{$path}.oneOf[{$index}]");

                        return;
                    } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
                        // Try the next oneOf branch.
                    }
                }

                $this->fail("{$path}: matched no oneOf branch.");
            }
        } elseif (is_array($data) && isset($schema->items)) {
            foreach (array_values($data) as $index => $item) {
                $this->assertSchemaValid($item, $schema->items, "{$path}[{$index}]");
            }
        }
    }

    protected function assertOneOfIsAList(\stdClass $schema): void
    {
        foreach ($schema->properties ?? [] as $key => $property) {
            if (isset($property->oneOf)) {
                $this->assertIsArray($property->oneOf, "{$key}.oneOf must be an array.");
                $this->assertNotEmpty($property->oneOf, "{$key}.oneOf must not be empty.");
            }

            if (isset($property->properties) || isset($property->items)) {
                $this->assertOneOfIsAList($property);
            }
        }
    }
}