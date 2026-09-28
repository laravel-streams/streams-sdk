<?php

namespace Streams\Sdk\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Streams\Sdk\Support\DocsIndex;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search-docs')]
#[Description('Keyword search over local Streams documentation: the app\'s docs/ folder, docs shipped with installed streams/* packages, and docs streams in streams/data. Returns ranked matches with an ID, title, and snippet. Pass an ID to read-doc for the full page. Search before guessing at Streams APIs or config keys.')]
#[IsReadOnly]
class SearchDocs extends StreamsTool
{
    public function __construct(protected DocsIndex $docs)
    {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Keywords, for example "relationship field config" or "filebase source".')->required(),
            'limit' => $schema->integer()->min(1)->max(25)->description('Maximum results (1-25). Defaults to 8.'),
        ];
    }

    protected function respond(Request $request): Response
    {
        $request->validate([
            'query' => 'required|string',
            'limit' => 'nullable|integer|min:1|max:25',
        ]);

        $results = $this->docs->search($request->get('query'), (int) ($request->get('limit') ?: 8));

        if (!$results) {
            return $this->json([
                'results' => [],
                'hint' => count($this->docs->documents())
                    ? 'No matches. Try fewer or different keywords.'
                    : 'No documentation was found. Configure streams.sdk.mcp.docs.paths.',
            ]);
        }

        return $this->json(['results' => $results]);
    }
}
