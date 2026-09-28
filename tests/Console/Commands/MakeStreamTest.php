<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Streams\Sdk\Tests\SdkTestCase;

class MakeStreamTest extends SdkTestCase
{
    public function test_it_writes_a_validated_definition()
    {
        $this->artisan('make:stream', [
            'id' => 'notes',
            '--description' => 'Short notes.',
        ])
            ->expectsOutputToContain('streams/notes.json')
            ->assertSuccessful();

        $definition = json_decode(file_get_contents($this->basePath('streams/notes.json')), true);

        $this->assertSame('Notes', $definition['name']);
        $this->assertSame('Short notes.', $definition['description']);
        $this->assertSame('https://streams.dev/schema/streams.schema.json', $definition['$schema']);
        $this->assertSame('uuid', $definition['fields'][0]['type']);
    }

    public function test_it_refuses_to_overwrite_unless_forced()
    {
        $this->artisan('make:stream', ['id' => 'posts'])
            ->expectsOutputToContain('already exists')
            ->expectsOutputToContain('--force')
            ->assertFailed();

        $this->assertStringContainsString('Blog posts.', file_get_contents($this->basePath('streams/posts.json')));

        $this->artisan('make:stream', [
            'id' => 'posts',
            '--force' => true,
            '--name' => 'Replaced',
        ])->assertSuccessful();

        $definition = json_decode(file_get_contents($this->basePath('streams/posts.json')), true);

        $this->assertSame('Replaced', $definition['name']);
    }

    public function test_it_rejects_unsafe_ids()
    {
        $this->artisan('make:stream', ['id' => '../escape'])
            ->expectsOutputToContain('snake_case')
            ->assertFailed();

        $this->assertFileDoesNotExist($this->basePath('streams/../escape.json'));
    }
}
