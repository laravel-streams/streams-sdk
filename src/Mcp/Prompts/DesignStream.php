<?php

namespace Streams\Sdk\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;
use Streams\Sdk\Support\Playbooks;

/**
 * The modeling loop, in the order that works: look at what exists, draft the
 * smallest stream that answers the question, validate it, then seed it.
 */
#[Name('design-stream')]
#[Title('Model a new stream')]
#[Description('Walks an agent through adding one stream to this app: inspect what exists, draft the definition, validate it, create it, seed it, and generate types.')]
class DesignStream extends Prompt
{
    public function arguments(): array
    {
        return [
            new Argument('domain', 'The data you are modeling, in a sentence ("support tickets with a status and an assignee").', false),
        ];
    }

    public function handle(Request $request): Response
    {
        $domain = trim((string) $request->get('domain')) ?: 'the data you were asked to model';

        $recipe = Playbooks::get('add-a-stream') ?? [];

        return Response::text(<<<MARKDOWN
            Model a new Streams stream, one step at a time. Do not write a file until step 3.

            The request: {$domain}

            1. Look before you draft. Call streams-status (what is installed, what is registered) and list-streams. A field you were asked to add may already exist on another stream; a relationship to it is cheaper than a copy of it.
            2. Write the smallest definition that answers the question. Every field gets its type and its rules, because those rules are the validation your app will apply: `required`, `unique`, and field config are data, not code. Use `uuid` for `id` with `config.default: true` unless the app already has another key convention (check `key` in describe-stream).
            3. Check it against this app before creating it: validate-stream-definition with the draft. Fix every error; read each warning.
            4. Create it: make-stream. It refuses an invalid definition, refuses an existing id unless you pass force, and registers what it writes.
            5. Prove it works: create one real entry with create-entry, then read it back with read-entry. If a rule makes a sensible entry fail, the rule is wrong, not the data.
            6. Regenerate types so editors and agents follow the new shape: `php artisan streams:schema --include={id}`.
            7. Stop. A panel, an API, and a table are separate steps — `how-to` has each one.

            Rules that keep this clean:
            - The definition file in streams/ is the source of truth. Generated PHP is generated; do not hand-edit it, regenerate it.
            - Do not rename a field an existing entry uses without saying so: existing data keeps the old handle and will fail a new required rule.
            - Never invent a field handle on an existing stream. describe-stream first.

            Recipe from this app's own playbook:
            MARKDOWN
            ."\n".(string) json_encode($recipe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
