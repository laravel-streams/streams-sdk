<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Streams\Sdk\Console\Make\BuildStage;
use Streams\Sdk\Console\Make\Kinds\KindRegistry;
use Streams\Sdk\Console\Make\Kinds\KindRegistryException;
use Streams\Sdk\Console\Make\Paths;
use Streams\Sdk\Support\AppTree;
use Streams\Sdk\Support\AppTreeException;

/**
 * Build generated code from the app JSON tree.
 *
 * This is the only build command. Component kinds are nodes in the tree
 * (people.table, people.pages.manage, people.actions.add) and are reached
 * through this command. There is no streams:build command and no
 * per-component make:* command.
 */
class StreamsMake extends Command
{
    protected $signature = 'streams:make
        {path? : Dot path to build, such as people or people.table. Omit to build the whole tree.}
        {--tree= : App streams.json. Defaults to config streams.sdk.make.tree.}
        {--output= : Directory generated files are written under. Defaults to config streams.sdk.make.output.}
        {--dry-run : Print the unified diff and plan without writing files.}
        {--json : Print the plan as JSON: created, modified, skipped, unapplied, conflicts, and kinds.}';

    protected $description = 'Compare the app JSON tree with the code, then add or edit generated files.';

    public function handle(): int
    {
        $registry = $this->laravel->make(KindRegistry::class);

        try {
            $treeFile = $this->locate($this->option('tree') ?: (string) config('streams.sdk.make.tree', base_path('streams.json')));
            $output = $this->locate($this->option('output') ?: (string) config('streams.sdk.make.output', base_path()));
            $tree = AppTree::load($treeFile, $registry->treeContainers());
            $path = $this->argument('path');
            $only = is_string($path) && $path !== '' ? $path : null;

            if ($only !== null && ! $tree->has($only)) {
                $this->error("Unknown app tree node [{$only}].");

                return self::FAILURE;
            }
        } catch (AppTreeException|KindRegistryException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $stage = new BuildStage(Paths::fromConfig());
        $plan = $stage->plan($tree, $only, $output);

        if (! $this->option('dry-run')) {
            $stage->apply($plan, $output);
        }

        if ($this->option('json')) {
            $payload = $plan->toArray();
            $payload['kinds'] = $this->resolvedKinds($registry, $tree, $only);

            $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $diff = $plan->unifiedDiff();
            $this->line($diff === '' ? '(no changes)' : $diff);
        } else {
            foreach ($plan->created as $file) {
                $this->line("<info>created:</info> {$file}");
            }

            foreach ($plan->modified as $file) {
                $this->line("<info>modified:</info> {$file}");
            }
        }

        foreach ($plan->skipped as $file) {
            $this->line("<fg=yellow>skipped:</> {$file}");
        }

        foreach ($plan->unapplied as $edit) {
            $this->line("<fg=yellow>unapplied:</> {$edit['path']} {$edit['file']} {$edit['reason']}");
        }

        foreach ($plan->conflicts as $conflict) {
            $this->line("<fg=yellow>conflict:</> {$conflict['path']} {$conflict['file']}");
        }

        $this->line(sprintf(
            'created: %d modified: %d skipped: %d unapplied: %d conflicts: %d',
            count($plan->created),
            count($plan->modified),
            count($plan->skipped),
            count($plan->unapplied),
            count($plan->conflicts),
        ));

        return self::SUCCESS;
    }

    /**
     * Kinds for the nodes in scope. Each entry names the kind and any
     * input-schema errors. This does not register a command.
     *
     * @return array<string, array{kind: string, source: string, package?: string, errors: list<array{field: string, message: string}>}>
     */
    protected function resolvedKinds(KindRegistry $registry, AppTree $tree, ?string $only): array
    {
        $resolved = [];

        foreach ($tree->types() as $path => $type) {
            if ($path === '' || ! $this->pathInScope($path, $only)) {
                continue;
            }

            $kind = $registry->forType($type);

            if ($kind === null) {
                continue;
            }

            $errors = $kind->validate($tree->node($path));
            $entry = [
                'kind' => $kind->name,
                'source' => (string) $kind->source,
                'errors' => $errors,
            ];

            if ($kind->package !== null) {
                $entry = [
                    'kind' => $kind->name,
                    'source' => (string) $kind->source,
                    'package' => $kind->package,
                    'errors' => $errors,
                ];
            }

            $resolved[$path] = $entry;
        }

        ksort($resolved);

        return $resolved;
    }

    protected function pathInScope(string $path, ?string $only): bool
    {
        if ($only === null || $only === '') {
            return true;
        }

        return $path === $only || str_starts_with($path, $only.'.');
    }

    /**
     * Absolute paths are used as given. Relative paths resolve from the app base.
     */
    protected function locate(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
