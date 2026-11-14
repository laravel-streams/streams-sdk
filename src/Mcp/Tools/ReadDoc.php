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
 * The whole page, so an agent does not act on a snippet.
 */
#[Name('read-doc')]
#[Title('Read an installed docs page')]
#[Description('Return one installed docs page in full: frontmatter summary plus the markdown body. Pass {"id": "docs/packages/sdk/commands.md"} exactly as search-docs reported it, or a short form like "commands.md" when it is unambiguous. Reading beats guessing from a snippet; quote what you used when you cite it.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ReadDoc extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:300'],
        ], [
            'id.required' => 'Pass a page id from search-docs, for example {"id": "docs/packages/sdk/commands.md"}.',
        ]);

        $page = DocsLibrary::find((string) $validated['id']);

        if ($page === null) {
            $near = DocsLibrary::search((string) $validated['id'], null, 5);

            return Response::error(
                'No installed page ['.$validated['id'].'].'
                .($near !== [] ? ' Closest: '.implode(', ', array_column($near, 'id')).'.' : ' Run search-docs to find the right id.'),
            );
        }

        return $this->data([
            'id' => $page['id'],
            'title' => $page['title'],
            'description' => $page['description'],
            'package' => $page['package'],
            'tags' => $page['tags'],
            'status' => $page['status'],
            'word_count' => str_word_count($page['body']),
            'body' => $page['body'],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Page id as search-docs reported it, e.g. "docs/packages/core/fields.md".')->required(),
        ];
    }
}
