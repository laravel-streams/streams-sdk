<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class StubPublish extends Command
{
    protected $signature = 'streams:stub:publish
        {--force : Overwrite existing published stubs.}
        {--dry-run : List the files that would be published without copying.}';

    protected $description = 'Publish the versioned SDK stub set into the app so generated output can be overridden.';

    public function handle(): int
    {
        $source = __DIR__ . '/stubs';

        $target = rtrim((string) config('streams.sdk.make.stubs_dir', base_path('streams/stubs')), '/');

        $files = $this->files($source);

        if (!$files) {
            $this->error('No stubs were found to publish.');

            return self::FAILURE;
        }

        foreach ($files as $sourceFile) {
            $relative = substr($sourceFile, strlen($source) + 1);

            $targetFile = $target . '/' . $relative;

            if (!$this->option('force') && !$this->option('dry-run') && File::exists($targetFile)) {
                $this->line("<fg=yellow>exists (skipped):</> {$targetFile}");

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("<info>publish:</info> {$targetFile}");

                continue;
            }

            File::ensureDirectoryExists(dirname($targetFile));

            File::copy($sourceFile, $targetFile);

            $this->line("<info>published:</info> {$targetFile}");
        }

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (!$this->option('force')) {
            $this->line('');
            $this->components->info('Published stubs. Re-run with --force to overwrite existing files.');
        }

        return self::SUCCESS;
    }

    /**
     * The stub files, deterministic and shallow-first.
     *
     * @return list<string>
     */
    protected function files(string $source): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $files = [];

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}