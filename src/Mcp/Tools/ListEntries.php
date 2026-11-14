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
use Streams\Sdk\Support\StreamFacts;
use Throwable;

/**
 * Criteria, exposed. The same query API works on flat files and on a database
 * table, so an agent that learns it once is done learning storage.
 */
#[Name('list-entries')]
#[Title('Read entries with criteria')]
#[Description('Read entries from a stream using Criteria — the same where/orderBy/limit API on flat files, a database table, or an Eloquent model. Pass {"stream": "people", "where": [{"field": "status", "operator": "=", "value": "active"}], "order_by": "name", "limit": 25}. Unknown field names are refused with the real field list instead of returning an empty result. Values of fields marked protected are never returned. Call describe-stream first so you filter on a field that exists.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ListEntries extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'stream' => ['required', 'string'],
            'where' => ['nullable', 'array'],
            'order_by' => ['nullable', 'string'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['string'],
        ], [
            'stream.required' => 'Pass a stream id, for example {"stream": "people"}.',
            'fields.*.string' => 'fields must be a list of field handles, for example ["id", "title"].',
        ]);

        $stream = $this->stream((string) $validated['stream']);

        if ($stream === null) {
            return $this->unknownStream((string) $validated['stream']);
        }

        $where = [];

        foreach ((array) ($validated['where'] ?? []) as $clause) {
            if (! is_array($clause)) {
                return Response::error('Each where clause must be an object: {"field": "status", "operator": "=", "value": "active"}.');
            }

            $where[] = [
                'field' => (string) ($clause['field'] ?? ''),
                'operator' => (string) ($clause['operator'] ?? '='),
                'value' => $clause['value'] ?? null,
            ];
        }

        try {
            return $this->data((new EntryWriter())->list(
                (string) $stream->id,
                $where,
                isset($validated['order_by']) ? (string) $validated['order_by'] : null,
                (string) ($validated['direction'] ?? 'asc'),
                (int) ($validated['limit'] ?? 25),
                (int) ($validated['offset'] ?? 0),
                array_values(array_map('strval', (array) ($validated['fields'] ?? []))),
            ));
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'stream' => $schema->string()->description('Stream id.')->required(),
            'where' => $schema->array()->description('Clauses to filter by, ANDed together: [{"field": "status", "operator": "=", "value": "active"}]. Operators: = != > >= < <= like not_like in not_in is_null. Field names must exist on the stream.'),
            'order_by' => $schema->string()->description('Field handle to sort by.'),
            'direction' => $schema->string()->enum(['asc', 'desc'])->description('Sort direction. Default asc.'),
            'limit' => $schema->integer()->min(1)->max(100)->description('Maximum entries to return. Default 25, hard cap 100.'),
            'offset' => $schema->integer()->min(0)->description('Skip this many entries, for paging with limit.'),
            'fields' => $schema->array()->description('Field handles to return, for example ["id", "title"]. The key field is always included. Omit for every non-protected field. Values longer than 300 characters are shortened either way — read-entry returns the full value.'),
        ];
    }
}
