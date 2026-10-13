<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Streams\Sdk\Tests\SdkTestCase;

class ShowEntryTest extends SdkTestCase
{

    public function test_it_can_show_an_entry()
    {
        $this->artisan('entries:show', [
            'stream' => 'films',
            'entry' => 4,
            '--show' => 'title,episode_id',
        ])->assertSuccessful()
            ->expectsOutputToContain('A New Hope')
            ->expectsOutputToContain('episode_id');
    }
}
