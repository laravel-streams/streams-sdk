<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Streams\Sdk\Support\StreamInspector;

class ListStreams extends Command
{

    /**
     * @inheritDoc
     *
     * @var string
     */
    protected $signature = 'streams:list
        {--json : Output JSON for scripts and agents.}';

    protected $description = 'List registered streams.';

    public function handle(StreamInspector $inspector)
    {
        $streams = $inspector->summaries();

        if ($this->option('json')) {
            return $this->line(json_encode($streams, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $this->table(
            ['ID', 'Name', 'Source', 'Fields', 'Description'],
            array_map(fn ($stream) => [
                $stream['id'],
                $stream['name'],
                $stream['source'],
                count($stream['fields']),
                (string) $stream['description'],
            ], $streams)
        );
    }
}
