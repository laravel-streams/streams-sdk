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
use Streams\Sdk\Support\DocsLibrary;

/**
 * Search the docs that are installed *in this app*, so the answer matches the
 * version actually running. No network, no stale memory.
 */
#[Name('search-docs')]
#[Title('Search the installed Streams docs')]
#[Description('Search the documentation installed in this app: your own docs/, plus the docs shipped inside streams/core, ui, api, sdk, and testing — the version you are actually running. Every word in the query must match; titles rank above body text. Returns id, title, description, package, tags, and a snippet. Pass the id to read-doc for the whole page. Nothing is fetched from the network; if a page is missing here, https://streams.dev/llms.txt is the published index.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class SearchDocs extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:200'],
            'package' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ], [
            'query.required' => 'Pass a search query, for example "criteria where" or "field type select".',
        ]);

        $results = DocsLibrary::search(
            (string) $validated['query'],
            isset($validated['package']) ? (string) $validated['package'] : null,
            (int) ($validated['limit'] ?? 8),
        );

        if ($results === []) {
            return $this->data([
                'query' => $validated['query'],
                'count' => 0,
                'results' => [],
                'note' => 'No installed page matches every word. Try fewer words, or one word: '.implode(', ', array_slice(array_keys($this->packages()), 0, 6)).' are the packages with pages here.',
            ]);
        }

        return $this->data([
            'query' => $validated['query'],
            'count' => count($results),
            'results' => $results,
            'next' => 'read-doc {"id": "<id>"} for the full page.',
        ]);
    }

    /**
     * @return array<string, int>
     */
    protected function packages(): array
    {
        $counts = [];

        foreach (DocsLibrary::index() as $page) {
            $counts[$page['package']] = ($counts[$page['package']] ?? 0) + 1;
        }

        return $counts;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Words to search for. Case-insensitive; every word must appear somewhere on the page.')->required(),
            'package' => $schema->string()->description('Limit to one package\'s pages: core, ui, api, sdk, testing, client, dev, or app (your own docs/).'),
            'limit' => $schema->integer()->min(1)->max(25)->description('Maximum results. Default 8.')->default(8),
        ];
    }
}
