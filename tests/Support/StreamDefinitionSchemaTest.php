<?php

namespace Streams\Sdk\Tests\Support;

use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Opis\JsonSchema\Errors\ErrorFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use Streams\Sdk\Support\StreamDefinitionValidator;
use Streams\Sdk\Support\StreamGenerator;

class StreamDefinitionSchemaTest extends TestCase
{
    public static function definitions(): array
    {
        $root = dirname(__DIR__, 2);

        $files = array_merge(
            glob($root . '/streams/*.json'),
            glob($root . '/resources/streams/*.json'),
            glob($root . '/tests/fixtures/streams/*.json'),
            glob($root . '/vendor/streams/core/resources/streams/*.json'),
        );

        return array_combine(
            array_map(fn ($file) => str_replace($root . '/', '', $file), $files),
            array_map(fn ($file) => [$file], $files)
        );
    }

    #[DataProvider('definitions')]
    public function test_shipped_definitions_match_the_schema(string $file)
    {
        $result = (new Validator())->validate(
            json_decode(file_get_contents($file)),
            json_decode(file_get_contents(StreamDefinitionValidator::schemaPath()))
        );

        $this->assertTrue(
            $result->isValid(),
            $result->isValid() ? '' : json_encode((new ErrorFormatter())->format($result->error()), JSON_PRETTY_PRINT)
        );
    }

    public function test_the_schema_is_valid_json_with_a_stable_id()
    {
        $schema = StreamDefinitionValidator::schema();

        $this->assertSame(StreamDefinitionValidator::SCHEMA_ID, $schema['$id']);
        $this->assertSame('http://json-schema.org/draft-07/schema#', $schema['$schema']);
    }

    public function test_generated_definitions_point_at_the_schema()
    {
        $definition = (new StreamGenerator(new StreamDefinitionValidator()))->definition('notes');

        $this->assertSame(StreamDefinitionValidator::SCHEMA_ID, $definition['$schema']);
    }

    public function test_related_lists_self_entries_and_input_hints_match_the_schema()
    {
        $definition = [
            'name' => 'Comments',
            'config' => ['source' => ['type' => 'self']],
            'data' => [
                'one' => ['id' => 'one', 'body' => 'Hi'],
            ],
            'fields' => [
                [
                    'handle' => 'id',
                    'type' => 'slug',
                    'required' => true,
                ],
                [
                    'handle' => 'body',
                    'type' => 'string',
                    'docs' => 'https://streams.dev/docs/core/fields',
                    'input' => ['type' => 'editor'],
                ],
                [
                    'handle' => 'commentable',
                    'type' => 'polymorphic',
                    'config' => ['related' => ['posts', 'pages']],
                ],
                [
                    'handle' => 'author_id',
                    'type' => 'relationship',
                    'config' => ['related' => 'authors', 'multiple' => true],
                ],
            ],
        ];

        $result = (new Validator())->validate(
            json_decode(json_encode($definition)),
            json_decode(file_get_contents(StreamDefinitionValidator::schemaPath()))
        );

        $this->assertTrue(
            $result->isValid(),
            $result->isValid() ? '' : json_encode((new ErrorFormatter())->format($result->error()), JSON_PRETTY_PRINT)
        );
    }

    public static function routeConstraintShapes(): array
    {
        return [
            'object of parameter to regex' => [['id' => '[^./]+'], true],
            'list of parameter objects' => [[['id' => '[^./]+']], true],
            'string' => ['[^./]+', false],
        ];
    }

    #[DataProvider('routeConstraintShapes')]
    public function test_route_constraints_accept_an_object_or_a_list_of_objects(mixed $constraints, bool $valid)
    {
        $definition = [
            'routes' => [[
                'handle' => 'view',
                'uri' => 'docs/{id}',
                'view' => 'docs',
                'constraints' => $constraints,
            ]],
        ];

        $result = (new Validator())->validate(
            json_decode(json_encode($definition)),
            json_decode(file_get_contents(StreamDefinitionValidator::schemaPath()))
        );

        $message = $result->isValid()
            ? 'expected this constraints value to be rejected'
            : json_encode((new ErrorFormatter())->format($result->error()), JSON_PRETTY_PRINT);

        $this->assertSame($valid, $result->isValid(), $message);

        if ($valid) {
            return;
        }

        $paths = array_keys((new ErrorFormatter())->format($result->error()));

        $this->assertNotEmpty(array_filter(
            $paths,
            fn ($path) => str_contains((string) $path, 'constraints')
        ), $message);
    }

    public static function fieldImportShapes(): array
    {
        return [
            'whole fields value' => ['@streams/fields/common.json', true],
            'one field in list form' => [['@streams/fields/title.json', ['handle' => 'id', 'type' => 'slug']], true],
            'one field in object form' => [['seo' => '@streams/fields/seo.json', 'title' => 'string'], true],
            'path without @' => ['streams/fields/common.json', false],
            'non-JSON import' => [['@streams/fields/title.yaml'], false],
        ];
    }

    #[DataProvider('fieldImportShapes')]
    public function test_fields_accept_imports(mixed $fields, bool $valid)
    {
        $this->assertSchemaResult(['fields' => $fields], $valid, 'fields');
    }

    public static function objectAllowedShapes(): array
    {
        return [
            'stream' => [[['stream' => 'posts']], true],
            'generic and prototype classes' => [[['generic' => 'Illuminate\Support\Collection'], ['prototype' => 'App\Prototypes\Address']], true],
            'list of strings' => [['posts'], false],
            'field type object' => [[['type' => 'number']], false],
            'empty object' => [[[]], false],
        ];
    }

    #[DataProvider('objectAllowedShapes')]
    public function test_object_allowed_items_name_a_stream_generic_or_prototype(array $allowed, bool $valid)
    {
        $this->assertSchemaResult([
            'fields' => [[
                'handle' => 'meta',
                'type' => 'object',
                'config' => ['allowed' => $allowed],
            ]],
        ], $valid, 'allowed');
    }

    protected function assertSchemaResult(array $definition, bool $valid, string $path): void
    {
        // Encode empty PHP arrays inside "allowed" as JSON objects.
        $json = json_encode($definition);
        $json = str_replace('"allowed":[[]]', '"allowed":[{}]', $json);

        $result = (new Validator())->validate(
            json_decode($json),
            json_decode(file_get_contents(StreamDefinitionValidator::schemaPath()))
        );

        $message = $result->isValid()
            ? 'expected this definition to be rejected: ' . $json
            : json_encode((new ErrorFormatter())->format($result->error()), JSON_PRETTY_PRINT);

        $this->assertSame($valid, $result->isValid(), $message);

        if ($valid) {
            return;
        }

        $this->assertNotEmpty(array_filter(
            array_keys((new ErrorFormatter())->format($result->error())),
            fn ($pointer) => str_contains((string) $pointer, $path)
        ), $message);
    }
}
