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
use Streams\Sdk\Support\StreamFacts;

/**
 * The typed contract for one stream. This is the call that replaces guessing:
 * fields, resolved rules, source, key, routes, and the file to edit.
 */
#[Name('describe-stream')]
#[Title('Describe one stream')]
#[Description('Read before you write code or data against a stream. Returns the source (type, path/table/model) so you know storage is not your concern, the key field, every field with its type, whether it is required or unique, its resolved validation rules and relevant config, route definitions, the entry count, and the definition file path to edit. Read-only fields (fields marked protected) are reported as protected and their values are never returned. Call this instead of inferring a field handle from sample data or a file name.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class DescribeStream extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'stream' => ['required', 'string'],
            'with_rules' => ['nullable', 'boolean'],
        ], [
            'stream.required' => 'Pass a stream id, for example {"stream": "people"}. Call list-streams if you are not sure it exists.',
        ]);

        $stream = $this->stream((string) $validated['stream']);

        if ($stream === null) {
            return $this->unknownStream((string) $validated['stream']);
        }

        return $this->data(StreamFacts::describe($stream, (bool) ($validated['with_rules'] ?? true)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'stream' => $schema->string()
                ->description('Stream id, exactly as list-streams reports it.')
                ->required(),
            'with_rules' => $schema->boolean()
                ->description('Include resolved validation rules per field. Default true; set false for a shorter read.'),
        ];
    }
}
