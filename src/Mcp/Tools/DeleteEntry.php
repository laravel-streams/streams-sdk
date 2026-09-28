<?php

namespace Streams\Sdk\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Streams\Sdk\Support\EntryManager;
use Streams\Sdk\Support\StreamInspector;
use Streams\Sdk\Mcp\Concerns\WritesToApplication;

#[Name('delete-entry')]
#[Description('Permanently delete an entry from a stream by its key. For file-based sources this deletes data from the working tree.')]
#[IsDestructive]
class DeleteEntry extends StreamsTool
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

        $this->entries->delete($stream, $id = $request->get('id'));

        return $this->json(['deleted' => true, 'stream' => $stream->id, 'id' => $id]);
    }
}
