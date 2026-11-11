<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Streams\Sdk\Console\Onboard\OnboardRecipe;

/**
 * SALLT / Streams create-project onboarding TUI.
 *
 * Path 1 (this command): after composer create-project installs streams/core +
 * streams/sdk, discover generation options and write streams.onboard.json.
 * No templates yet — options drive future streams:make recipes.
 *
 * Path 2 (later): JS/front-end create flow; can ask whether a Streams API /
 * client already exists to discover (same recipe keys).
 */
class StreamsOnboard extends Command
{
    protected $signature = 'streams:onboard
                            {--surface=laravel : Create path: laravel|js}
                            {--defaults : Minimal recipe without prompts}
                            {--kitchen-sink : Full option set without prompts}
                            {--json : Print the recipe as JSON}
                            {--no-write : Do not write streams.onboard.json}';

    /** @var list<string> */
    protected $aliases = ['sallt:onboard'];

    protected $description = '🧂 Discover SALLT generation options (TUI); write streams.onboard.json';

    public function handle(): int
    {
        $surface = $this->option('surface') === 'js' ? 'js' : 'laravel';

        $this->components->info('🧂 SALLT onboard — Streams · Alpine · Laravel · Livewire · Tailwind');
        $this->line('Surface: <comment>'.$surface.'</comment> (path '.($surface === 'laravel' ? '1 — Laravel create-project' : '2 — JS / front-end').')');
        $this->newLine();

        if ($this->option('kitchen-sink')) {
            $recipe = OnboardRecipe::fromArray([
                'surface' => $surface,
                'frontend' => true,
                'panels' => 1,
                'multi_tenancy' => true,
                'api' => true,
                'discover_streams_api' => false,
                'streams_api_base_url' => null,
                'sample_domain' => 'Todos',
            ]);
        } elseif ($this->option('defaults') || ! $this->input->isInteractive()) {
            $recipe = OnboardRecipe::fromArray([
                'surface' => $surface,
                'frontend' => false,
                'panels' => 0,
                'multi_tenancy' => false,
                'api' => false,
                'discover_streams_api' => false,
                'streams_api_base_url' => null,
                'sample_domain' => null,
            ]);
        } else {
            $recipe = $this->discoverInteractively($surface);
        }

        if ($this->option('json')) {
            $this->line(json_encode($recipe->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info('Recipe options');
            $this->table(
                ['Key', 'Value'],
                collect($recipe->toArray())
                    ->map(fn ($value, $key) => [$key, is_bool($value) ? ($value ? 'yes' : 'no') : ($value ?? '—')])
                    ->values()
                    ->all(),
            );

            $this->newLine();
            $this->components->info('Planned generation (not emitted yet)');
            $this->table(
                ['Step', 'Status', 'Note'],
                collect($recipe->plannedSteps())
                    ->map(fn (array $step) => [$step['id'], $step['status'], $step['note']])
                    ->all(),
            );
        }

        if (! $this->option('no-write')) {
            $path = OnboardRecipe::path();
            $recipe->write($path);
            $this->newLine();
            $this->components->success("Wrote {$path}");
            $this->line('Next: implement KindRegistry recipes against these options; then re-run generation.');
            $this->line('Golden app later: regenerate <comment>RyanThompson/todos</comment> from this recipe and rebase onto it.');
        }

        return self::SUCCESS;
    }

    protected function discoverInteractively(string $surface): OnboardRecipe
    {
        $this->line('No starter templates — only options that generators will honor.');
        $this->newLine();

        if ($surface === 'js') {
            $this->components->warn('JS / front-end path is scaffolding-only; coordinate with a Laravel SALLT API when present.');
        }

        $frontend = $surface === 'js'
            ? true
            : $this->confirm('Do you need a frontend (Alpine / Livewire surfaces)?', true);

        $panel = $surface === 'laravel'
            ? $this->confirm('Do you need a control panel?', true)
            : false;
        $panels = $panel
            ? max(1, (int) $this->ask('How many panels?', '1'))
            : 0;

        $tenancy = $this->confirm('Is this multi-tenant?', false);

        $discover = $this->confirm('Do you have a Streams API / client to discover?', $surface === 'js');
        $apiUrl = null;
        $api = false;

        if ($discover) {
            $apiUrl = $this->ask('Streams API base URL (blank to probe later)', '');
            $apiUrl = is_string($apiUrl) && trim($apiUrl) !== '' ? trim($apiUrl) : null;
            $api = true;
        } elseif ($surface === 'laravel') {
            $api = $this->confirm('Do you need a REST API edge on this app?', $frontend === false);
        }

        $sample = null;
        if ($surface === 'laravel' && $this->confirm('Scaffold a sample Todos domain when generators exist (plumbing only)?', true)) {
            $sample = 'Todos';
        }

        return OnboardRecipe::fromArray([
            'surface' => $surface,
            'frontend' => $frontend,
            'panels' => $panels,
            'multi_tenancy' => $tenancy,
            'api' => $api,
            'discover_streams_api' => $discover,
            'streams_api_base_url' => $apiUrl,
            'sample_domain' => $sample,
        ]);
    }
}
