<?php

namespace Streams\Sdk\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Streams\Sdk\Mcp\Tool;
use Streams\Sdk\Support\DefinitionWriter;

/**
 * What a definition file is allowed to contain, from the same schema an editor
 * uses. Pairs with validate-stream-definition: read this, write that.
 */
#[Name('definition-schema')]
#[Title('Stream definition schema')]
#[Description('The JSON Schema a stream definition file must satisfy, plus the field types this app can use and the conventions the generators follow (file name is the id, snake_case handles, config.source for storage). Read this before drafting a new definition; then send your draft to validate-stream-definition before you write the file. This is the same document shipped at https://streams.dev/schema/streams.schema.json and referenced by the "$schema" key in every streams/*.json file.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class DefinitionSchema extends Tool
{
    public function handle(): Response|ResponseFactory
    {
        $path = DefinitionWriter::schemaPath();

        if (! is_file($path)) {
            return Response::error('The stream definition schema is missing from the SDK install.');
        }

        $schema = json_decode((string) file_get_contents($path), true);

        return $this->data([
            'schema' => is_array($schema) ? $schema : null,
            'source' => DefinitionWriter::SCHEMA,
            'field_types' => array_keys((array) config('streams.core.field_types', [])),
            'conventions' => [
                'A definition lives at streams/{id}.json and the file name is the id.',
                'Field handles are snake_case: a lowercase letter, then lowercase letters, numbers, underscores.',
                'Every field carries its own rules, so validation is data: {"handle": "email", "type": "email", "required": true}.',
                'Storage is config.source: {"type": "filebase", "format": "json"} is the default; use {"type": "database", "table": "..."} or {"type": "eloquent", "model": "..."} to sit on existing data.',
                'config.key_name picks the entry key; a uuid id field with config.default true is the usual choice.',
            ],
            'next' => 'validate-stream-definition {"definition": ...} checks a draft without writing anything.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
