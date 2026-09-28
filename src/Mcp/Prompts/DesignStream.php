<?php

namespace Streams\Sdk\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Description;

#[Name('design-stream')]
#[Description('Guide an agent through modeling a new domain concept as a Streams stream: inspect, design, validate, create, and seed.')]
class DesignStream extends Prompt
{
    public function arguments(): array
    {
        return [
            new Argument('requirements', 'What the stream should model, in plain language.', true),
            new Argument('id', 'Preferred snake_case stream ID.', false),
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate(['requirements' => 'required|string']);

        $id = $request->get('id') ? "Use the stream ID `{$request->get('id')}`." : 'Choose a short, plural, snake_case stream ID.';

        return Response::text(<<<MARKDOWN
            Model the following as a Laravel Streams stream.

            Requirements: {$request->get('requirements')}

            {$id}

            Work through these steps with the Streams MCP tools:

            1. Call `list-streams` to see existing streams. Reuse or relate to them instead of duplicating concepts.
            2. Call `definition-schema` and, if anything is unclear, `search-docs` (for example "fields", "relationship", "sources").
            3. Draft the definition: a list of fields, each with `handle` and `type`. Mark required fields with `"required": true`.
               Put type options under `config`: `config.related` for relationship fields, `config.options` for select fields.
            4. Call `validate-stream-definition` with the draft and fix every error before continuing.
            5. Call `make-stream` to write `streams/{id}.json`.
            6. Optionally call `create-entry` with one or two realistic sample entries, then `list-entries` to confirm.

            Summarize the final definition and any assumptions you made.
            MARKDOWN);
    }
}
