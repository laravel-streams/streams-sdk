<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Streams\Sdk\Console\Make\BuildStage;
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
        {--json : Print the plan as JSON: created, modified, skipped, and unapplied.}';

    protected $description = 'Compare the app JSON tree with the code, then add or edit generated files.';

    public function handle(): int
    {
        try {
            $treeFile = $this->locate($this->option('tree') ?: (string) config('streams.sdk.make.tree', base_path('streams.json')));
            $output = $this->locate($this->option('output') ?: (string) config('streams.sdk.make.output', base_path()));
            $tree = AppTree::load($treeFile);
            $path = $this->argument('path');
            $only = is_string($path) && $path !== '' ? $path : null;

            if ($only !== null && ! $tree->has($only)) {
                $this->error("Unknown app tree node [{$only}].");

                return self::FAILURE;
            }
        } catch (AppTreeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $stage = new BuildStage(Paths::fromConfig());
        $plan = $stage->plan($tree, $only, $output);

        if (! $this->option('dry-run')) {
            $stage->apply($plan, $output);
        }

        if ($this->option('json')) {
            $this->output->writeln(json_encode($plan->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

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

        $this->line(sprintf(
            'created: %d modified: %d skipped: %d unapplied: %d',
            count($plan->created),
            count($plan->modified),
            count($plan->skipped),
            count($plan->unapplied),
        ));

        return self::SUCCESS;
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
