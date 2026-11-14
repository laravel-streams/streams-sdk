<?php

namespace Streams\Sdk\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Streams\Sdk\Mcp\Concerns\IsWriteTool;
use Streams\Sdk\Mcp\Tool;
use Streams\Sdk\Support\EntryWriter;
use Throwable;

/**
 * Writes go through the stream's validator, and only through it. An agent gets
 * the same rejection a form would show, in the same words, before anything is
 * saved.
 */
#[Name('create-entry')]
#[Title('Create an entry')]
#[Description('Create one entry in a stream. Pass {"stream": "people", "attributes": {"name": "Ada", "email": "ada@example.com"}}. The stream\'s own rules run first: unknown field handles are refused (with the real list), protected fields cannot be set here, and a failing rule returns the exact messages and writes nothing. Check describe-stream for fields, required flags, and rules before you call this. Read the result back with read-entry.')]
#[IsOpenWorld(false)]
class CreateEntry extends Tool
{
    use IsWriteTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'stream' => ['required', 'string'],
            'attributes' => ['required', 'array'],
        ], [
            'stream.required' => 'Pass a stream id, for example {"stream": "people", "attributes": {"name": "Ada"}}.',
            'attributes.required' => 'Pass the field values as "attributes": {"handle": value, ...}.',
        ]);

        try {
            return $this->data((new EntryWriter())->create(
                (string) $validated['stream'],
                (array) $validated['attributes'],
            ));
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'stream' => $schema->string()->description('Stream id.')->required(),
            'attributes' => $schema->object()->description('Field handle => value. Only handles that exist on the stream, no protected fields.')->required(),
        ];
    }
}
