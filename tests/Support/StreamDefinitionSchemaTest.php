<?php

namespace Streams\Sdk\Tests\Support;

use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Opis\JsonSchema\Errors\ErrorFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use Streams\Sdk\Support\StreamDefinitionValidator;

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

    public function test_the_make_stream_stub_points_at_the_schema()
    {
        $stub = file_get_contents(dirname(__DIR__, 2) . '/src/Console/Commands/stubs/stream.stub');

        $this->assertStringContainsString(StreamDefinitionValidator::SCHEMA_ID, $stub);
    }
}
