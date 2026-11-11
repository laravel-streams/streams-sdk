<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Streams\Core\Stream\Stream;
use Streams\Core\Support\Facades\Streams;
use Streams\Sdk\Tests\SdkTestCase;

class StreamsTuiTest extends SdkTestCase
{
    public function test_exit_leaves_the_main_menu(): void
    {
        $this->artisan('streams:tui')
            ->expectsChoice('SALLT', 'Exit', ['Streams', 'Exit'])
            ->assertSuccessful();
    }

    public function test_back_returns_from_the_stream_table_to_the_main_menu(): void
    {
        $ids = Streams::collection()
            ->sortBy(fn (Stream $stream) => (string) $stream->id)
            ->map(fn (Stream $stream) => (string) $stream->id)
            ->values()
            ->all();

        $this->assertNotEmpty($ids);

        $this->artisan('streams:tui')
            ->expectsChoice('SALLT', 'Streams', ['Streams', 'Exit'])
            ->expectsChoice('Select a stream', 'Back', ['Back', ...$ids])
            ->expectsChoice('SALLT', 'Exit', ['Streams', 'Exit'])
            ->assertSuccessful();
    }

    public function test_a_stream_landing_shows_its_handle_and_returns(): void
    {
        $ids = Streams::collection()
            ->sortBy(fn (Stream $stream) => (string) $stream->id)
            ->map(fn (Stream $stream) => (string) $stream->id)
            ->values()
            ->all();

        $handle = $ids[0];

        $this->artisan('streams:tui')
            ->expectsChoice('SALLT', 'Streams', ['Streams', 'Exit'])
            ->expectsChoice('Select a stream', $handle, ['Back', ...$ids])
            ->expectsOutputToContain($handle)
            ->expectsChoice($handle, 'Back', ['Back'])
            ->expectsChoice('Select a stream', 'Back', ['Back', ...$ids])
            ->expectsChoice('SALLT', 'Exit', ['Streams', 'Exit'])
            ->assertSuccessful();
    }

    public function test_non_interactive_lists_streams_and_exits(): void
    {
        $this->artisan('streams:tui', ['--no-interaction' => true])
            ->expectsOutputToContain('people')
            ->assertSuccessful();
    }
}
