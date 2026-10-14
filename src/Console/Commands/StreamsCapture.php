<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Streams\Sdk\Console\Capture\CaptureException;
use Streams\Sdk\Console\Capture\CaptureReader;

/**
 * Capture existing builder settings into the app JSON tree.
 *
 * Reads panels, pages, tables and actions from builder settings (static
 * properties and setUp()/getPages()/navigation() chains) in the known
 * reference layout, and writes a streams.json. Literal settings become
 * data; closures, permission callbacks, class resolvers and computed
 * options become opaque nodes pointing at their source file and line.
 * Opaque nodes are owned by people and streams:make never rewrites them.
 */
class StreamsCapture extends Command
{
    protected $signature = 'streams:capture
        {--root= : App root to read builder files from. Defaults to config streams.sdk.capture.root, then base_path().}
        {--tree= : Where to write the captured streams.json. Defaults to config streams.sdk.capture.tree, then base_path(streams.json).}
        {--dry-run : Print the captured tree without writing it.}
        {--json : Print the captured tree as JSON.}';

    protected $description = 'Capture existing builder settings into the app JSON tree.';

    public function handle(): int
    {
        $root = (string) ($this->option('root') ?: config('streams.sdk.capture.root', base_path()));
        $treeFile = (string) ($this->option('tree') ?: config('streams.sdk.capture.tree', base_path('streams.json')));

        try {
            $reader = new CaptureReader($this->locate($root));
            $tree = $reader->capture();
        } catch (CaptureException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $json = json_encode($tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            $this->error('The captured tree could not be encoded as JSON.');

            return self::FAILURE;
        }

        if ($this->option('json') || $this->option('dry-run')) {
            $this->output->writeln($json);

            return self::SUCCESS;
        }

        $directory = dirname($this->locate($treeFile));

        if (! is_dir($directory)) {
            $this->error("Cannot write the tree: directory [{$directory}] does not exist.");

            return self::FAILURE;
        }

        file_put_contents($this->locate($treeFile), $json."\n");

        $this->line("<info>captured:</info> {$this->locate($treeFile)}");

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
