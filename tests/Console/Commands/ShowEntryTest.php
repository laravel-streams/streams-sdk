<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Streams\Sdk\Tests\SdkTestCase;

class ShowEntryTest extends SdkTestCase
{

    public function test_it_can_show_an_entry()
    {
        $this->withFixtureStreams('posts', 'authors');

        $this->artisan('entries:show', [
            'stream' => 'posts',
            'entry' => 'hello-world',
            '--show' => 'title,status',
        ])->assertSuccessful()
            ->expectsOutputToContain('Hello World')
            ->expectsOutputToContain('status');
    }
}
