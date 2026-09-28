<?php

namespace Streams\Sdk\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Streams\Sdk\Support\EntryManager;
use Streams\Sdk\Support\StreamInspector;

#[Name('list-entries')]
#[Description('Query a stream\'s entries with optional where constraints, ordering, and pagination. Returns total, page, per_page, last_page, and entries. Protected fields are omitted.')]
#[IsReadOnly]
class ListEntries extends StreamsTool
{
    public function __construct(
        protected StreamInspector $inspector,
        protected EntryManager $entries
    ) {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'stream' => $schema->string()->description('The stream ID.')->required(),
            'where' => $schema->array()->items($schema->object([
                'field' => $schema->string()->description('Field handle.')->required(),
                'operator' => $schema->string()->description('Comparison operator: =, !=, <, <=, >, >=, LIKE, IN. Defaults to =.'),
                'value' => $schema->union(['string', 'integer', 'number', 'boolean', 'array', 'null'])->description('Value to compare. Use an array with IN. Use % wildcards with LIKE.'),
            ]))->description('Constraints combined with AND.'),
            'order_by' => $schema->string()->description('Field handle to sort by.'),
            'direction' => $schema->string()->enum(['asc', 'desc'])->description('Sort direction. Defaults to asc.'),
            'limit' => $schema->integer()->min(1)->max(100)->description('Entries per page (1-100). Defaults to 15.'),
            'page' => $schema->integer()->min(1)->description('Page number. Defaults to 1.'),
        ];
    }

    protected function respond(Request $request): Response
    {
        $request->validate([
            'stream' => 'required|string',
            'where' => 'nullable|array',
            'where.*.field' => 'required|string',
            'order_by' => 'nullable|string',
            'direction' => 'nullable|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $stream = $this->inspector->find($request->get('stream'));

        $result = $this->entries->list($stream, $request->all());

        $result['entries'] = array_map(
            fn ($entry) => $this->inspector->entry($entry),
            $result['entries']
        );

        return $this->json(['stream' => $stream->id] + $result);
    }
}
