<?php

namespace Streams\Sdk\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Streams\Sdk\Mcp\Concerns\IsWriteTool;
use Streams\Sdk\Mcp\Tool;
use Streams\Sdk\Support\EntryWriter;
use Throwable;

/**
 * The one tool that cannot be undone, so it echoes what was lost and is hidden
 * entirely by STREAMS_MCP_READ_ONLY.
 */
#[Name('delete-entry')]
#[Title('Delete an entry')]
#[Description('Permanently delete one entry by key. There is no trash and no undo: the response echoes the entry that was removed so you can recreate it by hand if that was not the plan. Confirm the key with read-entry first, and never delete entries you did not create in this session without asking the person who owns the app. Hidden completely when STREAMS_MCP_READ_ONLY=true.')]
#[IsDestructive]
#[IsOpenWorld(false)]
class DeleteEntry extends Tool
{
    use IsWriteTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'stream' => ['required', 'string'],
            'key' => ['required'],
        ], [
            'stream.required' => 'Pass a stream id, for example {"stream": "people", "key": "1"}.',
            'key.required' => 'Pass the entry key to delete.',
        ]);

        try {
            return $this->data((new EntryWriter())->delete(
                (string) $validated['stream'],
                is_scalar($validated['key']) ? (string) $validated['key'] : json_encode($validated['key']),
            ));
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'stream' => $schema->string()->description('Stream id.')->required(),
            'key' => $schema->string()->description('The value of the stream\'s key field.')->required(),
        ];
    }
}
