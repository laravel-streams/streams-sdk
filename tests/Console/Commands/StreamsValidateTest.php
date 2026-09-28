<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Streams\Sdk\Tests\SdkTestCase;

class StreamsValidateTest extends SdkTestCase
{
    public function test_it_passes_valid_definitions()
    {
        $this->artisan('streams:validate')
            ->expectsOutputToContain('streams/posts.json')
            ->expectsOutputToContain('0 invalid')
            ->assertSuccessful();
    }

    public function test_it_fails_invalid_definitions()
    {
        file_put_contents($this->basePath('streams/broken.json'), json_encode([
            'fields' => [
                ['handle' => 'body', 'type' => 'wysiwyg'],
            ],
        ]));

        $this->artisan('streams:validate', ['paths' => ['streams/broken.json']])
            ->expectsOutputToContain('Field type [wysiwyg] is not registered')
            ->assertFailed();
    }
}
