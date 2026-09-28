<?php

namespace Streams\Sdk\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Streams\Sdk\Support\StreamInspector;

#[Name('entry-schema')]
#[Description('Get the JSON Schema (OpenAPI flavored) that a stream\'s entries must satisfy: property types, formats, enums, and required properties. This is what `php artisan streams:schema` writes to disk. For the schema of stream definition files, use definition-schema instead.')]
#[IsReadOnly]
class EntrySchema extends StreamsTool
{
    public function __construct(protected StreamInspector $inspector)
    {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'stream' => $schema->string()->description('The stream ID.')->required(),
        ];
    }

    protected function respond(Request $request): Response
    {
        $request->validate(['stream' => 'required|string']);

        return $this->json($this->inspector->entrySchema(
            $this->inspector->find($request->get('stream'))
        ));
    }
}
