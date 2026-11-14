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
 * Partial update, still fully validated: the merged entry is what rules run
 * against, so a two-field patch cannot quietly break a required one.
 */
#[Name('update-entry')]
#[Title('Update an entry')]
#[Description('Update the fields you pass on one entry, leaving the rest alone. Pass {"stream": "people", "key": "1", "attributes": {"status": "alumni"}}. The merged result is validated against the stream\'s rules before anything is saved, so patching one field cannot break a required one. Unknown and protected handles are refused. The response lists what changed and the entry as it now stands.')]
#[IsOpenWorld(false)]
class UpdateEntry extends Tool
{
    use IsWriteTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'stream' => ['required', 'string'],
            'key' => ['required'],
            'attributes' => ['required', 'array', 'min:1'],
        ], [
            'stream.required' => 'Pass a stream id, for example {"stream": "people", "key": "1", "attributes": {"status": "alumni"}}.',
            'key.required' => 'Pass the entry key to change.',
            'attributes.min' => 'Pass at least one field to change.',
        ]);

        try {
            return $this->data((new EntryWriter())->update(
                (string) $validated['stream'],
                is_scalar($validated['key']) ? (string) $validated['key'] : json_encode($validated['key']),
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
            'key' => $schema->string()->description('The value of the stream\'s key field.')->required(),
            'attributes' => $schema->object()->description('Only the handles you want to change.')->required(),
        ];
    }
}
