<?php

namespace Streams\Sdk\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Description;
use Streams\Sdk\Support\EntryManager;
use Streams\Sdk\Support\StreamInspector;
use Streams\Sdk\Mcp\Concerns\WritesToApplication;

#[Name('create-entry')]
#[Description('Create an entry in a stream. Attributes are validated with the stream\'s field rules (the same rules shown by describe-stream) after field defaults such as generated UUIDs are applied. Fails if an entry with the same key exists. The MCP equivalent of `php artisan make:entry`.')]
class CreateEntry extends StreamsTool
{
    use WritesToApplication;

    public function __construct(
        protected StreamInspector $inspector,
        protected EntryManager $entries
    ) {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'stream' => $schema->string()->description('The stream ID.')->required(),
            'attributes' => $schema->object()->description('Field handle => value.')->required(),
        ];
    }

    protected function respond(Request $request): Response
    {
        $request->validate([
            'stream' => 'required|string',
            'attributes' => 'present|array',
        ]);

        $stream = $this->inspector->find($request->get('stream'));

        return $this->json($this->inspector->entry(
            $this->entries->create($stream, $request->get('attributes'))
        ));
    }
}
