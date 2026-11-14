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
use Streams\Core\Support\Facades\Streams;
use Streams\Sdk\Mcp\Tool;
use Streams\Sdk\Support\StreamFacts;

/**
 * What is modeled here, cheaply. Field handles are included so the next call
 * can be describe-stream on the right stream instead of a guess.
 */
#[Name('list-streams')]
#[Title('List streams')]
#[Description('Every stream registered in this app: id, name, description, source, key field, field handles, and the definition file path. Use it to find the stream you want; call describe-stream for the rules before you write code against it. A definition file that is not in this list did not register — see streams-status.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ListStreams extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'with_entry_counts' => ['nullable', 'boolean'],
        ], [
            'limit.integer' => 'limit must be a whole number between 1 and 200.',
        ]);

        if (! Streams::collection()->isEmpty()) {
            $summaries = StreamFacts::summaries((int) ($validated['limit'] ?? 200));

            if (! empty($validated['with_entry_counts'])) {
                $summaries = array_map(function (array $summary) {
                    $summary['entries'] = StreamFacts::count(Streams::make($summary['id']));

                    return $summary;
                }, $summaries);
            }
        } else {
            $summaries = [];
        }

        if ($summaries === []) {
            return $this->data([
                'streams' => [],
                'next' => 'No streams are registered. Create one with make-stream, or run `php artisan streams:onboard` and read the how-to recipe "add-a-stream".',
            ]);
        }

        return $this->data([
            'count' => count($summaries),
            'streams' => $summaries,
            'next' => 'describe-stream {stream} for fields and the rules Core will apply.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Maximum streams to return. Default 200.')->min(1)->max(200)->default(200),
            'with_entry_counts' => $schema->boolean()->description('Also count entries per stream. Costs one query per stream, so leave it off on large apps.'),
        ];
    }
}
