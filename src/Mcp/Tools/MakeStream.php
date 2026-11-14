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
use Streams\Sdk\Support\DefinitionWriter;
use Throwable;

/**
 * The only sanctioned way for an agent to create a definition: validate first,
 * write second, register third. A stub that never registers is the classic
 * beginner wall, so this tool will not produce one.
 */
#[Name('make-stream')]
#[Title('Create a stream definition')]
#[Description('Create streams/{id}.json from a definition you provide. Order of operations: the definition is validated against the installed Core (same rules as validate-stream-definition), the file is written with the "$schema" pointer for editor autocomplete, and the stream is registered for this process. An invalid definition is refused and nothing is written; an existing id is refused unless you pass force. Pass {"id": "tickets", "definition": {"name": "Tickets", "fields": [...]}} — id is snake_case and becomes both the file name and the stream id. Prefer the design-stream prompt if you have not modeled a stream here before.')]
#[IsOpenWorld(false)]
class MakeStream extends Tool
{
    use IsWriteTool;

    /**
     * Resolved lazily so the class stays `new`-able (the MCP test helpers and
     * any caller outside the container construct tools with no arguments).
     */
    public function __construct(protected ?DefinitionWriter $writer = null)
    {
    }

    protected function writer(): DefinitionWriter
    {
        return $this->writer ??= app(DefinitionWriter::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'definition' => ['required', 'array'],
            'force' => ['nullable', 'boolean'],
        ], [
            'id.required' => 'Pass a snake_case id, for example {"id": "tickets", "definition": {"name": "Tickets", "fields": [{"handle": "subject", "type": "string"}]}}.',
            'definition.required' => 'Pass the definition body: at minimum "name" and "fields".',
        ]);

        try {
            return $this->data($this->writer()->write(
                (string) $validated['id'],
                (array) $validated['definition'],
                (bool) ($validated['force'] ?? false),
            ));
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('snake_case stream id: a lowercase letter, then lowercase letters, numbers, and underscores. Becomes streams/{id}.json and the queryable id.')->required(),
            'definition' => $schema->object()->description('The definition body: name, description, config.source, and fields. See definition-schema for what is allowed.')->required(),
            'force' => $schema->boolean()->description('Replace an existing definition file. Default false, and refusing is what keeps a second agent from overwriting your work.'),
        ];
    }
}
