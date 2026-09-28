<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Streams\Sdk\Tests\SdkTestCase;
use Illuminate\Support\Facades\Artisan;

class ListStreamsTest extends SdkTestCase
{
    public function test_it_lists_streams()
    {
        $this->artisan('streams:list')
            ->expectsOutputToContain('Authors')
            ->expectsOutputToContain('Blog posts.')
            ->assertSuccessful();
    }

    public function test_it_lists_streams_as_json()
    {
        Artisan::call('streams:list', ['--json' => true]);

        $streams = collect(json_decode(Artisan::output(), true))->keyBy('id');

        $this->assertSame('filebase', $streams['posts']['source']);
        $this->assertContains('title', $streams['posts']['fields']);
        $this->assertTrue($streams->has('authors'));
    }
}
