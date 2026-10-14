<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Console\Command;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;
use Streams\Sdk\Console\Support\PhpEditor;
use Streams\Sdk\Console\Support\Pint;
use Streams\Sdk\Console\Support\Stub;

class MakeTable extends Command
{
    protected $signature = 'make:table
        {id : The table stream id.}
        {--panel : Register the table in the application panel.}
        {--page : Register a public page for the table.}
        {--force : Overwrite an existing table file.}
        {--dry-run : Print the changes that would be applied without writing anything.}';

    protected $description = 'Make a table stream and register it in an application panel or site page.';

    public function handle(): int
    {
        $id = $this->argument('id');

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $id)) {
            $this->error("The table id [{$id}] must be snake_case (lowercase letters, numbers, and underscores).");

            return self::FAILURE;
        }

        $flags = array_filter([
            'panel' => (bool) $this->option('panel'),
            'page' => (bool) $this->option('page'),
        ]);

        $this->makeTableFile($id);

        if ($this->option('panel') || $this->option('page')) {
            $this->registerInRegistry($id, $flags);
        }

        $this->newLine();

        $this->components->info('Done.');

        return self::SUCCESS;
    }

    /**
     * The directory tables are written to.
     */
    protected function tablesDir(): string
    {
        return rtrim((string) config('streams.sdk.tables_dir', base_path('streams')), '/');
    }

    /**
     * The PHP structure file tables are registered in.
     */
    protected function registryPath(): string
    {
        return (string) config('streams.sdk.tables_path', base_path('app/Streams/tables.php'));
    }

    protected function makeTableFile(string $id): void
    {
        $path = $this->tablesDir() . '/' . $id . '.json';

        $contents = Stub::make('table', [
            'id' => $id,
            'name' => ucwords(str_replace(['-', '_'], ' ', $id)),
            'description' => '',
        ])->render() . PHP_EOL;

        if (File::exists($path) && !$this->option('force')) {
            $this->line("<fg=yellow>exists (skipped):</> {$path}");

            return;
        }

        $this->write($path, $contents);
    }

    protected function registerInRegistry(string $id, array $flags): void
    {
        $path = $this->registryPath();

        if (File::exists($path)) {
            $original = (string) File::get($path);
            $editor = PhpEditor::read($path);
        } else {
            $original = '';
            $editor = PhpEditor::fromString($this->registryStub(), $path);
        }

        $editor->put($id, $flags);

        $this->write($path, $editor->print(), $original);
    }

    protected function registryStub(): string
    {
        return Stub::make('table.registry')->render() . PHP_EOL;
    }

    /**
     * Write a file, or — with --dry-run — print the exact diff the write applies.
     */
    protected function write(string $path, string $contents, ?string $original = null): void
    {
        $original = $original === null ? '' : $original;

        if ($this->option('dry-run')) {
            $this->line($this->diff($path, $original, $contents));

            return;
        }

        if ($original !== '' && $original === $contents) {
            $this->line("<fg=yellow>no changes:</> {$path}");

            return;
        }

        File::ensureDirectoryExists(dirname($path));

        File::put($path, $contents);

        if (str_ends_with($path, '.php') && Pint::format($path)) {
            $this->line("<info>written:</info> {$path} (pint)");

            return;
        }

        $this->line("<info>written:</info> {$path}");
    }

    protected function diff(string $path, string $from, string $to): string
    {
        $differ = new Differ(new UnifiedDiffOutputBuilder("--- {$path}\n+++ {$path}\n"));

        return $differ->diff($from, $to);
    }
}