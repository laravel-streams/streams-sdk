<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Streams\Sdk\Tests\SdkTestCase;

class ListEntriesTest extends SdkTestCase
{

    public function test_it_can_list_entries()
    {
        $this->withFixtureStreams('posts', 'authors');

        $this->artisan('entries:list', [
            'stream' => 'posts',
            '--show' => 'title',
            '--per-page' => 50,
        ])->assertSuccessful()
            ->expectsOutputToContain('Hello World')
            ->expectsOutputToContain('Second Post');
    }
}
