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
use Streams\Sdk\Support\Playbooks;

/**
 * The "how do I…" answers, written against the commands this package actually
 * ships. Better than remembered Laravel idioms.
 */
#[Name('how-to')]
#[Title('How to do a Streams job')]
#[Description('Step-by-step recipes for the common jobs, using only commands that exist in this app: start-here (new SALLT app), add-a-stream (model new data), add-a-field (change a field), entries (read and write data), admin-panel (Livewire panel from the app tree), rest-api (expose a stream), agent-setup (connect an agent), typed-generation (keep types in sync). Call with no argument to list the recipes; call with {"recipe": "add-a-stream"} for that recipe\'s ordered steps — each step has the command, why it matters, the file to write, or a note.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class HowTo extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'recipe' => ['nullable', 'string'],
        ], [
            'recipe.string' => 'Pass a recipe handle, for example {"recipe": "add-a-stream"}.',
        ]);

        $handle = trim((string) ($validated['recipe'] ?? ''));

        if ($handle !== '') {
            $recipe = Playbooks::get($handle);

            if ($recipe === null) {
                return Response::error(
                    'Unknown recipe ['.$handle.']. Available: '.implode(', ', Playbooks::handles()).'.',
                );
            }

            return $this->data(array_merge(
                ['handle' => $handle],
                $recipe,
                ['docs' => 'https://streams.dev'.$recipe['docs']],
            ));
        }

        $recipes = Playbooks::all();

        return $this->data([
            'count' => count($recipes),
            'site' => 'https://streams.dev',
            'recipes' => collect($recipes)
                ->map(fn (array $recipe, string $key) => [
                    'handle' => $key,
                    'title' => $recipe['title'],
                    'when' => $recipe['when'],
                    'steps' => count($recipe['steps']),
                    'docs' => 'https://streams.dev'.$recipe['docs'],
                ])
                ->values()
                ->all(),
            'usage' => 'Call again with {"recipe": "add-a-stream"} for the steps.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'recipe' => $schema->string()
                ->description('One of: '.implode(', ', Playbooks::handles()).'. Omit for the index.')
                ->enum(Playbooks::handles()),
        ];
    }
}
