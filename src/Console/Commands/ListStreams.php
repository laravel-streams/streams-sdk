<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Streams\Sdk\Support\StreamFacts;

class ListStreams extends Command
{
    /**
     * Every stream the running app can actually resolve, read through the same
     * facts layer the MCP tools use, so the two surfaces never disagree.
     *
     * @var string
     */
    protected $signature = 'streams:list
        {--query= : Only streams whose id, name, description, or source contains this string.}
        {--show=id,name,description,fields,source : Columns to display.}
        {--json : Print the streams as a JSON array.}';

    protected $description = 'List registered streams.';

    public function handle()
    {
        $streams = StreamFacts::summaries();

        if ($query = $this->option('query')) {
            $streams = array_values(array_filter(
                $streams,
                fn (array $stream) => str_contains(
                    strtolower($stream['id'].' '.($stream['name'] ?? '').' '.($stream['description'] ?? '').' '.($stream['source'] ?? '')),
                    strtolower((string) $query)
                )
            ));
        }

        if ($this->option('json')) {
            $this->line(json_encode($streams, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        if ($streams === []) {
            $this->components->warn('No streams are registered. Write streams/{id}.json (see the make-stream MCP tool or streams:make), then run streams:validate.');

            return Command::SUCCESS;
        }

        $columns = array_map('trim', explode(',', (string) $this->option('show')));

        $rows = array_map(function (array $stream) use ($columns) {
            $row = [];

            foreach ($columns as $column) {
                $row[] = $column === 'fields'
                    ? (string) count($stream['fields'] ?? [])
                    : (string) ($stream[$column] ?? '');
            }

            return $row;
        }, $streams);

        $this->table($columns, $rows);
        $this->line(' <fg=default>'.count($streams).' stream(s). Entries: <options=bold>php artisan entries:list {stream}</>. One stream in full: <options=bold>--json</>, or the <options=bold>describe-stream</> MCP tool.</>');

        return Command::SUCCESS;
    }
}
