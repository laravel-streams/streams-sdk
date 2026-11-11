<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Streams\Core\Stream\Stream;
use Streams\Core\Support\Facades\Streams;

/**
 * Human menu for the same stream facts agents read through MCP.
 * Main menu, a stream table, then one landing page. Nothing else yet.
 */
class StreamsTui extends Command
{
    protected $signature = 'streams:tui';

    protected $description = '🧂 Browse registered streams.';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->renderTable($this->streams());
            $this->line('Interactive menu: php artisan streams:tui');

            return self::SUCCESS;
        }

        while (true) {
            $section = $this->choice('SALLT', ['Streams', 'Exit'], 'Streams');

            if ($section === 'Exit') {
                return self::SUCCESS;
            }

            $this->browseStreams();
        }
    }

    protected function browseStreams(): void
    {
        while (true) {
            $streams = $this->streams();
            $this->newLine();
            $this->components->info('Streams');

            if ($streams->isEmpty()) {
                $this->warn('No streams are registered.');
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

    protected function landing(Stream $stream): void
    {
        $this->newLine();
        $this->components->info((string) $stream->id);

        $source = (string) ($stream->config('source.type') ?: '—');
        $format = $stream->config('source.format');
        if (is_string($format) && $format !== '') {
            $source .= ' ('.$format.')';
        }

        $fields = $stream->fields
            ->map(fn ($field) => $field->handle.' ('.$field->type.')')
            ->implode(', ');

        $count = '—';
        try {
            $count = (string) $stream->entries()->count();
        } catch (\Throwable) {
            $count = '—';
        }

        $this->table(['', ''], [
            ['Name', (string) ($stream->name ?: '—')],
            ['Description', (string) ($stream->description ?: '—')],
            ['Source', $source],
            ['Fields', $fields !== '' ? $fields : '—'],
            ['Entries', $count],
        ]);

        $this->choice((string) $stream->id, ['Back'], 'Back');
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
                Str::limit((string) ($stream->description ?: ''), 56),
            ];
        }

        $this->table(['#', 'Handle', 'Name', 'Description'], $rows);
    }
}
