<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Streams\Core\Stream\Stream;
use Streams\Core\Support\Facades\Streams;
use Streams\Sdk\Support\DocsLibrary;
use Streams\Sdk\Support\Playbooks;
use Streams\Sdk\Support\ProjectStatus;
use Streams\Sdk\Support\StreamFacts;
use Throwable;

/**
 * The human mirror of the MCP server: the same facts from the same support
 * classes, in a menu instead of a tool call. Open it when you are lost, and
 * when you want to know what your agent is seeing.
 *
 * `--json` prints the machine version of the same screen, so scripts and
 * agents read one thing and people read another without drift.
 */
class StreamsTui extends Command
{
    protected $signature = 'streams:tui {--json : Print the status and stream list as JSON instead of a menu.}';

    protected $description = '🧂 SALLT menu: project status, streams, how-tos, docs, and agent setup.';

    /**
     * Commands the menu offers to run on the spot: read-only, or writing only
     * generated files. Anything that touches data stays manual.
     */
    protected const SAFE_PREFIXES = [
        'php artisan streams:validate',
        'php artisan streams:schema',
        'php artisan entries:list',
    ];

    public function handle(ProjectStatus $status): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode([
                'status' => $status->toArray(),
                'streams' => StreamFacts::summaries(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if (! $this->input->isInteractive()) {
            $this->renderStatus($status);
            $this->renderStreams();
            $this->line('Interactive menu: php artisan streams:tui');

            return self::SUCCESS;
        }

        while (true) {
            $section = $this->choice('SALLT', ['Status', 'Streams', 'How to', 'Docs', 'Agents', 'Exit'], 'Status');

            match ($section) {
                'Exit' => self::SUCCESS,
                'Status' => $this->renderStatus($status),
                'Streams' => $this->browseStreams(),
                'How to' => $this->browsePlaybooks(),
                'Docs' => $this->browseDocs(),
                'Agents' => $this->renderAgents($status),
                default => null,
            };

            if ($section === 'Exit') {
                return self::SUCCESS;
            }
        }
    }

    protected function renderStatus(ProjectStatus $status): void
    {
        $data = $status->toArray();

        $this->newLine();
        $this->components->info('Where this app stands');

        $this->table(['', ''], [
            ['Laravel', $data['app']['laravel'].' (PHP '.PHP_VERSION.', '.$data['app']['env'].')'],
            ...collect($data['packages'])
                ->map(fn (array $package, string $name) => [
                    $name,
                    $package['installed']
                        ? ($package['version'] ?? 'installed').' — '.$package['purpose']
                        : '<fg=red>not installed</> — '.$package['purpose'],
                ])
                ->values()
                ->all(),
            ['streams', $data['streams']['registered'].' registered from '.$data['streams']['definition_files'].' file(s) in streams/'],
            ['mcp', $data['mcp']['registered']
                ? 'server ['.$data['mcp']['handle'].'] registered'.($data['mcp']['read_only'] ? ' (read-only)' : '').' — '.$data['mcp']['start']
                : ($data['mcp']['library'] ? 'laravel/mcp present, server not registered' : 'no laravel/mcp — agents cannot see this app yet')],
        ]);

        foreach ($data['streams']['unregistered_definitions'] as $id) {
            $this->warn('streams/'.$id.'.json is not registered — no stream named ['.$id.'] can be queried.');
        }

        if ($data['streams']['registered_without_definition'] !== []) {
            $this->comment('Registered without a file in streams/ (PHP-registered, or loaded from a package): '
                .implode(', ', $data['streams']['registered_without_definition']));
        }

        $steps = $data['next_steps'];

        if ($steps === []) {
            $this->components->info('Nothing blocked. Pick a stream and keep building.');

            return;
        }

        $this->line('<fg=default>Next, in order:</>');

        foreach ($steps as $index => $step) {
            $this->newLine();
            $this->line('<options=bold>'.($index + 1).'. '.$step['why'].'</>');
            $this->line('   <fg=cyan>'.$step['command'].'</>');
            $this->line('   docs: '.$step['docs']);

            if ($this->isSafe($step['command'])) {
                if ($this->confirm('Run it now?', false)) {
                    $this->runStep($step['command']);
                }
            }
        }
    }

    protected function isSafe(string $command): bool
    {
        foreach (static::SAFE_PREFIXES as $prefix) {
            if (str_starts_with($command, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Run the step's own command, not a paraphrase of it, so what you see is
     * what ran.
     */
    protected function runStep(string $command): void
    {
        $parts = preg_split('/\s+/', Str::before($command, ' && ')) ?: [];

        // Only `php artisan <command> <args>` is runnable here; composer and
        // npm steps stay copy-paste on purpose.
        if (($parts[1] ?? '') !== 'artisan') {
            $this->warn('Run this one yourself: '.$command);

            return;
        }

        $arguments = [];
        $options = [];

        foreach (array_slice($parts, 2) as $token) {
            if (str_starts_with($token, '--')) {
                [$key, $value] = array_pad(explode('=', Str::after($token, '--'), 2), 2, true);
                $options[$key] = $value;
            } else {
                $arguments[] = $token;
            }
        }

        try {
            $this->call($arguments[0] ?? '', array_merge($arguments, $options));
        } catch (Throwable $e) {
            $this->error('That step failed: '.$e->getMessage());
        }
    }

    protected function browseStreams(): void
    {
        while (true) {
            $streams = $this->streams();
            $this->newLine();
            $this->components->info('Streams');

            if ($streams->isEmpty()) {
                $this->warn('No streams are registered yet.');
                $this->line('   <fg=cyan>php artisan streams:onboard</> — or the Status menu for the short version.');
                $this->choice('Streams', ['Back'], 'Back');

                return;
            }

            $this->renderTable($streams);

            $options = ['Back', ...$streams->map(fn (Stream $stream) => (string) $stream->id)->all()];
            $picked = $this->choice('Select a stream', $options, 'Back');

            if ($picked === 'Back') {
                return;
            }

            $stream = $streams->first(fn (Stream $stream) => (string) $stream->id === $picked);

            if ($stream instanceof Stream) {
                $this->landing($stream);
            }
        }
    }

    /**
     * The same describe-stream answer an agent gets, plus a look at real data.
     */
    protected function landing(Stream $stream): void
    {
        $facts = StreamFacts::describe($stream);

        $this->newLine();
        $this->components->info((string) $stream->id);

        $rows = [
            ['Name', (string) ($facts['name'] ?? '—')],
            ['Description', (string) ($facts['description'] ?? '—')],
            ['Source', (string) ($facts['source']['type'] ?? '—').(($facts['source']['path'] ?? '') !== '' ? ' — '.$facts['source']['path'] : '').(($facts['source']['table'] ?? '') !== '' ? ' — '.$facts['source']['table'] : '')],
            ['Key', (string) ($facts['key'] ?? 'id')],
            ['Entries', (string) ($facts['entry_count'] ?? '—')],
            ['Definition', (string) ($facts['definition'] ?? 'PHP-registered (no file)')],
        ];

        foreach ((array) ($facts['fields'] ?? []) as $handle => $field) {
            $flags = [];

            if (! empty($field['required'])) {
                $flags[] = 'required';
            }

            if (! empty($field['unique'])) {
                $flags[] = 'unique';
            }

            if (! empty($field['protected'])) {
                $flags[] = 'protected';
            }

            $rows[] = [
                '  '.$handle,
                (string) ($field['type'] ?? '').($flags !== [] ? '  ['.implode(', ', $flags).']' : '').
                (($field['rules'] ?? []) !== [] ? '  '.implode('|', $field['rules']) : ''),
            ];
        }

        $this->table(['', ''], $rows);

        $this->previewEntries($stream);

        $this->line('Read it as JSON: <fg=cyan>php artisan streams:tui --json</>, or ask your agent for describe-stream {"stream": "'.$stream->id.'"}.');

        $this->choice((string) $stream->id, ['Back'], 'Back');
    }

    protected function previewEntries(Stream $stream): void
    {
        try {
            $entries = $stream->entries()->limit(5)->get();
        } catch (Throwable $e) {
            $this->comment('Entries could not be read: '.$e->getMessage());

            return;
        }

        if ($entries->isEmpty()) {
            $this->comment('No entries yet.');

            return;
        }

        $key = (string) $stream->config('key_name', 'id');
        $handles = $stream->fields->pluck('handle')->take(5)->all();

        $rows = $entries->map(function ($entry) use ($stream, $key, $handles) {
            $attributes = StreamFacts::attributes($stream, $entry);

            return array_merge(
                [(string) ($attributes[$key] ?? '—')],
                array_map(fn ($handle) => Str::limit((string) (is_scalar($attributes[$handle] ?? null) ? $attributes[$handle] : json_encode($attributes[$handle] ?? null)), 28), $handles),
            );
        })->values()->all();

        $this->line('<fg=default>First entries (protected fields hidden):</>');
        $this->table(array_merge([$key], $handles), $rows);
    }

    protected function browsePlaybooks(): void
    {
        while (true) {
            $recipes = Playbooks::all();

            $picked = $this->choice('How to', ['Back', ...array_keys($recipes)], 'Back');

            if ($picked === 'Back') {
                return;
            }

            $recipe = $recipes[$picked];

            $this->newLine();
            $this->components->info($recipe['title']);
            $this->line($recipe['when']);
            $this->line('docs: '.ProjectStatus::SITE.$recipe['docs']);

            foreach ($recipe['steps'] as $index => $step) {
                $this->newLine();
                $this->line('<options=bold>'.($index + 1).'. '.$step['why'].'</>');

                if (isset($step['command'])) {
                    $this->line('   <fg=cyan>'.$step['command'].'</>');
                }

                if (isset($step['file'])) {
                    $this->line('   file: <options=bold>'.$step['file'].'</>');
                }

                if (isset($step['contents'])) {
                    $this->line((string) json_encode($step['contents'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }

                if (isset($step['note'])) {
                    $this->comment('   '.$step['note']);
                }
            }
        }
    }

    protected function browseDocs(): void
    {
        while (true) {
            $query = $this->ask('Search the docs installed in this app (empty to go back)');

            if ($query === null || trim($query) === '') {
                return;
            }

            $results = DocsLibrary::search(trim($query), null, 8);

            if ($results === []) {
                $this->warn('No installed page matches every word of "'.$query.'". Try one word.');

                continue;
            }

            $this->table(
                ['#', 'id', 'title', 'package'],
                collect($results)->map(fn (array $page, int $i) => [
                    $i + 1,
                    $page['id'],
                    Str::limit($page['title'], 40),
                    $page['package'],
                ])->values()->all(),
            );

            $picked = $this->choice('Read a page', ['Back', ...array_column($results, 'id')], 'Back');

            if ($picked === 'Back') {
                continue;
            }

            $page = DocsLibrary::find($picked);

            if ($page !== null) {
                $this->line((string) $page['body']);
            }
        }
    }

    /**
     * What an agent can see right now, and the one command that changes it.
     */
    protected function renderAgents(ProjectStatus $status): void
    {
        $data = $status->toArray();
        $mcp = $data['mcp'];

        $this->newLine();
        $this->components->info('Agents (MCP)');

        $this->table(['', ''], [
            ['laravel/mcp', $mcp['library'] ? 'installed' : 'not installed — composer require --dev laravel/mcp'],
            ['server', $mcp['registered'] ? '['.$mcp['handle'].'] registered' : 'not registered'],
            ['mode', $mcp['read_only'] ? 'read-only (write tools hidden)' : 'read and write'],
            ['start', $mcp['start']],
        ]);

        $this->line('Claude Code, project scope (writes .mcp.json for the whole team):');
        $this->line('   <fg=cyan>claude mcp add --scope project '.$mcp['handle'].' -- php artisan mcp:start '.$mcp['handle'].'</>');
        $this->newLine();
        $this->line('Docs your agent can read without leaving the app: <fg=cyan>search-docs</>, <fg=cyan>read-doc</>, <fg=cyan>how-to</>, <fg=cyan>streams-status</>.');
        $this->line('Published index: '.$data['docs']['index']);

        if (! $mcp['library']) {
            $this->newLine();
            $this->comment('Install laravel/mcp to turn these on. Streams needs it; the server code is already here.');
        }
    }

    /**
     * @return Collection<int, Stream>
     */
    protected function streams(): Collection
    {
        return Streams::collection()
            ->sortBy(fn (Stream $stream) => (string) $stream->id)
            ->values();
    }

    /**
     * @param  Collection<int, Stream>  $streams
     */
    protected function renderTable(Collection $streams): void
    {
        $rows = [];

        foreach ($streams as $index => $stream) {
            $rows[] = [
                $index + 1,
                (string) $stream->id,
                (string) ($stream->name ?: ''),
                StreamFacts::sourceLabel($stream),
                (string) $stream->fields->count(),
                Str::limit((string) ($stream->description ?: ''), 40),
            ];
        }

        $this->table(['#', 'Handle', 'Name', 'Source', 'Fields', 'Description'], $rows);
    }
}
