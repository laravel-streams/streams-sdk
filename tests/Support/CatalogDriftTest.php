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
}