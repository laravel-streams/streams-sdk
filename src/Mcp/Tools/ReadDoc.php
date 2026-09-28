<?php

namespace Streams\Sdk\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Streams\Sdk\Support\DocsIndex;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('read-doc')]
#[Description('Read a full documentation page by the ID returned from search-docs.')]
#[IsReadOnly]
class ReadDoc extends StreamsTool
{
    public function __construct(protected DocsIndex $docs)
    {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('A document ID from search-docs, for example "vendor/streams/core/docs/fields.md".')->required(),
        ];
    }

    protected function respond(Request $request): Response
    {
        $request->validate(['id' => 'required|string']);

        if (!$document = $this->docs->read($request->get('id'))) {
            return Response::error("Document [{$request->get('id')}] was not found. Use search-docs to find document IDs.");
        }

        return Response::text("<!-- {$document['id']} -->\n# {$document['title']}\n\n" . ltrim(preg_replace('/^#\s+.+\R/', '', ltrim($document['body']), 1)));
    }
}
