<?php

namespace Streams\Sdk\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Support\Arr;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Description;
use Streams\Sdk\Support\StreamGenerator;
use Streams\Sdk\Mcp\Concerns\WritesToApplication;

#[Name('make-stream')]
#[Description('Create a stream definition at streams/{id}.json and register it immediately so entry tools can use it. The definition is validated first (see definition-schema); nothing is written when it is invalid. Without "fields", a single generated uuid "id" field is used; without "config", entries are stored as JSON files in streams/data/{id}. The MCP equivalent of `php artisan make:stream`.')]
class MakeStream extends StreamsTool
{
    use WritesToApplication;

    public function __construct(protected StreamGenerator $generator)
    {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('snake_case stream ID. Also the file name.')->required(),
            'name' => $schema->string()->description('Human readable name.'),
            'description' => $schema->string()->description('What the stream models.'),
            'fields' => $schema->array()->items($schema->object())->description('Field objects, each with at least "handle" and "type". Example: {"handle": "title", "type": "string", "required": true}.'),
            'config' => $schema->object()->description('Stream config such as {"source": {"type": "filebase", "format": "json"}}.'),
            'routes' => $schema->union(['array', 'object'])->description('Optional routes, for example {"view": "posts/{id}"}.'),
            'overwrite' => $schema->boolean()->description('Replace an existing streams/{id}.json. Defaults to false.'),
        ];
    }

    protected function respond(Request $request): Response
    {
        $request->validate([
            'id' => 'required|string',
            'name' => 'nullable|string',
            'description' => 'nullable|string',
            'fields' => 'nullable|array',
            'config' => 'nullable|array',
            'routes' => 'nullable|array',
            'overwrite' => 'nullable|boolean',
        ]);

        $result = $this->generator->make(
            $request->get('id'),
            Arr::only($request->all(), ['name', 'description', 'fields', 'config', 'routes']),
            (bool) $request->get('overwrite', false)
        );

        return $this->json([
            'created' => $result['path'],
            'stream' => $result['stream']->id,
            'warnings' => $result['warnings'],
            'definition' => $result['definition'],
        ]);
    }
}
