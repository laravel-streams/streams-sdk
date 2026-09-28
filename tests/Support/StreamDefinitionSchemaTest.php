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
}
