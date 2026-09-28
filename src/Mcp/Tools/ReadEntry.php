<?php

namespace Streams\Sdk\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Streams\Sdk\Support\EntryManager;
use Streams\Sdk\Support\StreamInspector;

#[Name('read-entry')]
#[Description('Read one entry from a stream by its key (the stream\'s key_name, usually "id"). Protected fields are omitted.')]
#[IsReadOnly]
class ReadEntry extends StreamsTool
{
    public function __construct(
        protected StreamInspector $inspector,
        protected EntryManager $entries
    ) {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'stream' => $schema->string()->description('The stream ID.')->required(),
            'id' => $schema->union(['string', 'integer'])->description('The entry key.')->required(),
        ];
    }

    protected function respond(Request $request): Response
    {
        $request->validate([
            'stream' => 'required|string',
            'id' => 'required',
        ]);

        $stream = $this->inspector->find($request->get('stream'));

        return $this->json($this->inspector->entry(
            $this->entries->find($stream, $request->get('id'))
        ));
    }
}
