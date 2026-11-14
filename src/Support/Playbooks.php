<?php

namespace Streams\Sdk\Support;

/**
 * The handful of things a beginner (or an agent) asks how to do, answered with
 * the commands that actually exist in this package. One source for the
 * `how-to` MCP tool, the `streams:tui` menu, and `php artisan streams:guide`,
 * so the three can never disagree.
 *
 * Rules for adding a recipe:
 * - Only commands a user can run today. If a step needs a generator that is
 *   not shipped, say so in `note` instead of naming a command.
 * - Order the steps the way a person should do them: model, check, generate,
 *   then use.
 * - Keep `why` to one sentence that explains the Streams way, not the flag.
 */
class Playbooks
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'start-here' => [
                'title' => 'Start a Streams app from scratch (SALLT)',
                'when' => 'A new project, or an existing app with nothing modeled yet.',
                'docs' => '/docs/sallt',
                'steps' => [
                    [
                        'why' => 'SALLT is the Laravel 12 create-project that installs Core and the SDK, keys the app, and runs onboard.',
                        'command' => "composer create-project ryanthompson/sallt my-app --repository='{\"type\":\"vcs\",\"url\":\"https://github.com/RyanThompson/SALLT\"}'",
                    ],
                    [
                        'why' => 'Onboard writes streams.onboard.json (frontend, panels, tenancy, API, sample domain) and applies the steps that are implemented.',
                        'command' => 'php artisan streams:onboard',
                    ],
                    [
                        'why' => 'Confirm every definition registers and validates before you build on it.',
                        'command' => 'php artisan streams:validate --json',
                    ],
                    [
                        'why' => 'One JSON Schema per stream is what stops agents and editors from guessing your data shape.',
                        'command' => 'php artisan streams:schema',
                    ],
                ],
            ],
            'add-a-stream' => [
                'title' => 'Model new data (a stream)',
                'when' => 'You have a noun in your domain: tickets, recipes, devices, people.',
                'docs' => '/docs/core/streams',
                'steps' => [
                    [
                        'why' => 'A stream is one JSON file in streams/. Name it snake_case; the file name is the id. Fields carry their own rules, so validation is data, not code.',
                        'file' => 'streams/tickets.json',
                        'contents' => [
                            '$schema' => 'https://streams.dev/schema/streams.schema.json',
                            'name' => 'Tickets',
                            'description' => 'Support tickets.',
                            'config' => [
                                'source' => ['format' => 'json'],
                            ],
                            'fields' => [
                                ['handle' => 'id', 'type' => 'uuid', 'required' => true, 'unique' => true, 'config' => ['default' => true]],
                                ['handle' => 'subject', 'type' => 'string', 'required' => true],
                                ['handle' => 'status', 'type' => 'select', 'required' => true, 'config' => ['options' => ['open' => 'Open', 'closed' => 'Closed']]],
                            ],
                        ],
                    ],
                    [
                        'why' => 'Validate before you query. This checks source config against the installed Core, which is the version your code runs against.',
                        'command' => 'php artisan streams:validate --json',
                    ],
                    [
                        'why' => 'Regenerate the entry schema so types follow the definition.',
                        'command' => 'php artisan streams:schema --include=tickets',
                    ],
                    [
                        'why' => 'Read it back the way your app does. If describe-stream agrees with your file, the stream is live.',
                        'command' => 'php artisan entries:list tickets',
                    ],
                ],
            ],
            'add-a-field' => [
                'title' => 'Change a field',
                'when' => 'Add, rename, retype, or revalidate a field on an existing stream.',
                'docs' => '/docs/core/fields',
                'steps' => [
                    [
                        'why' => 'Edit the definition, not a migration. Fields are data; the source stays put.',
                        'file' => 'streams/{id}.json',
                        'note' => 'Add an entry to "fields": {"handle": "priority", "type": "select", "required": true}.',
                    ],
                    [
                        'why' => 'Existing entries are not rewritten. A new required field fails validation on old data until you backfill it.',
                        'command' => 'php artisan streams:validate --json',
                    ],
                    [
                        'why' => 'If the stream has a table or form in the app JSON tree, regenerate the generated files so UI follows the field.',
                        'command' => 'php artisan streams:make --dry-run --json',
                    ],
                ],
            ],
            'entries' => [
                'title' => 'Read and write entries',
                'when' => 'You need data in or out of a stream.',
                'docs' => '/docs/core/entries',
                'steps' => [
                    [
                        'why' => 'Criteria are the one query API, and they work the same on flat files and on a database table.',
                        'command' => 'php artisan entries:list tickets --query="where:status,open"',
                    ],
                    [
                        'why' => 'make:entry validates against the stream before saving, so bad data cannot land in your source.',
                        'command' => 'php artisan make:entry tickets "subject=Printer on fire&status=open"',
                        'note' => 'make:entry is not registered yet. Use the create-entry MCP tool, or $stream->create([...]) in tinker, until it is.',
                    ],
                ],
            ],
            'admin-panel' => [
                'title' => 'Give a stream an admin panel',
                'when' => 'Someone non-technical needs to manage the data.',
                'docs' => '/docs/ui/quick-start',
                'steps' => [
                    [
                        'why' => 'streams/ui turns the app JSON tree into Livewire panels, tables, and forms. The menu walks the tree; --dry-run plans without writing.',
                        'command' => 'php artisan streams:make --menu',
                    ],
                    [
                        'why' => 'Capture reads builder settings you wrote by hand back into the tree, so the tree stays the source of truth.',
                        'command' => 'php artisan streams:capture',
                    ],
                ],
            ],
            'rest-api' => [
                'title' => 'Expose a stream over REST',
                'when' => 'A front end, mobile app, or another service needs the data.',
                'docs' => '/docs/api/installation',
                'steps' => [
                    [
                        'why' => 'streams/api serves the same streams and the same criteria, so the API cannot drift from the app.',
                        'command' => 'composer require streams/api:1.0.x-dev',
                    ],
                    [
                        'why' => 'Only streams you allowlist are served.',
                        'file' => 'config/streams/api.php',
                        'note' => 'See /docs/api/configuration for the allowlist and auth options.',
                    ],
                    [
                        'why' => 'The JS client is generated from the same schemas, so a front end gets types for free.',
                        'command' => 'npm install @laravel-streams/api-client',
                    ],
                ],
            ],
            'agent-setup' => [
                'title' => 'Give an agent access (MCP)',
                'when' => 'You are pairing with Claude Code, Cursor, VS Code, or Codex.',
                'docs' => '/docs/mcp',
                'steps' => [
                    [
                        'why' => 'The server runs over stdio through Artisan, on your machine, with your app boot.',
                        'command' => 'composer require --dev laravel/mcp',
                    ],
                    [
                        'why' => 'Project scope writes .mcp.json, so everyone on the team gets the same tools.',
                        'command' => 'claude mcp add --scope project streams -- php artisan mcp:start streams',
                    ],
                    [
                        'why' => 'Call streams-status first. It reports what is installed, what is registered, and what to run next.',
                        'command' => 'php artisan mcp:start streams',
                        'note' => 'Your agent client starts the server; you do not run this by hand. Read-only mode: STREAMS_MCP_READ_ONLY=true.',
                    ],
                ],
            ],
            'typed-generation' => [
                'title' => 'Stay typed as the model changes',
                'when' => 'You want agents and CI to catch a bad field before it reaches runtime.',
                'docs' => '/docs/sdk/stream-schema',
                'steps' => [
                    [
                        'why' => 'Thin agent inference is the failure mode. Push answers into types: definitions, schemas, generated files.',
                        'command' => 'php artisan streams:schema',
                    ],
                    [
                        'why' => 'The definition schema is the same one your editor follows through "$schema", so mistakes surface while you type.',
                        'command' => 'curl -s https://streams.dev/schema/streams.schema.json | head -40',
                    ],
                    [
                        'why' => 'Fails the build when a definition stops matching the installed Core.',
                        'command' => 'php artisan streams:validate --json',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function handles(): array
    {
        return array_keys(static::all());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $handle): ?array
    {
        return static::all()[$handle] ?? null;
    }

    /**
     * Short label / description pairs for menus and tool descriptions.
     *
     * @return array<string, string>
     */
    public static function menu(): array
    {
        return collect(static::all())
            ->map(fn (array $recipe) => $recipe['title'].' — '.$recipe['when'])
            ->all();
    }
}
