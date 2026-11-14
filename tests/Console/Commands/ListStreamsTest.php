<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Streams\Sdk\Tests\SdkTestCase;

class ListStreamsTest extends SdkTestCase
{
    public function test_it_can_list_streams()
    {
        $this->withFixtureStreams('posts', 'authors');

        // Names are asserted through --json rather than the rendered table.
        // Nothing showed up here because the test app had no registered streams
        // at all: the fixture definitions live under tests/fixtures and Core
        // resolves a filebase source against the application, so they have to be
        // copied in first (withFixtureStreams). --json is also what agents read.
        $listed = json_decode(
            $this->captureArtisan('streams:list', ['--show' => 'id,name', '--json' => true]),
            true
        );

        $names = array_column($listed ?? [], 'name', 'id');

        $this->assertSame('Posts', $names['posts'] ?? null);
        $this->assertSame('Authors', $names['authors'] ?? null);
    }

    /**
     * streams:list has never had a --per-page option in either branch; the
     * listing is the whole catalog. The old assertion asked for an option that
     * does not exist, so the command failed with InvalidOptionException.
     */
    public function test_it_can_filter_the_listing()
    {
        $this->withFixtureStreams('posts', 'authors');

        $listed = json_decode(
            $this->captureArtisan('streams:list', ['--query' => 'auth', '--json' => true]),
            true
        );

        $this->assertSame(['authors'], array_column($listed ?? [], 'id'));
    }

    /**
     * Run a console command and return what it printed, for assertions that need
     * to parse the output instead of only asking whether a line appeared.
     */
    protected function captureArtisan(string $command, array $parameters = []): string
    {
        Artisan::call($command, $parameters);

        return Artisan::output();
    }
}
