<?php

namespace Streams\Sdk\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Streams\Sdk\Mcp\Tool;
use Streams\Sdk\Support\EntryWriter;
use Throwable;

/**
 * One entry, by key. The key field is whatever the stream says it is
 * (describe-stream reports it), which is why this tool never assumes `id`.
 */
#[Name('read-entry')]
#[Title('Read one entry')]
#[Description('Fetch a single entry by its key. The key field is the stream\'s own key (usually id, sometimes slug or email) — check describe-stream\'s "key" before guessing. Returns every attribute except fields marked protected. Use this to confirm a write landed rather than trusting a success message.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ReadEntry extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'stream' => ['required', 'string'],
            'key' => ['required'],
        ], [
            'stream.required' => 'Pass a stream id, for example {"stream": "people", "key": "1"}.',
            'key.required' => 'Pass the entry key, for example {"stream": "people", "key": "1"}.',
        ]);

        try {
            return $this->data((new EntryWriter())->read(
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
