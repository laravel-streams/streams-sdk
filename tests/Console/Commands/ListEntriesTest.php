<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Streams\Sdk\Tests\SdkTestCase;

class ListEntriesTest extends SdkTestCase
{

    public function test_it_can_list_entries()
    {
        $this->artisan('entries:list', [
            'stream' => 'films',
            '--show' => 'title',
            '--per-page' => 50,
        ])->assertSuccessful()
            ->expectsOutputToContain('A New Hope')
            ->expectsOutputToContain('The Empire Strikes Back');
    }
}
