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

    public function test_it_resolves_field_imports_like_core()
    {
        mkdir($this->basePath('resources/fields'), 0777, true);

        file_put_contents($this->basePath('resources/fields/seo.json'), json_encode([
            ['handle' => 'meta_title', 'type' => 'string'],
            ['handle' => 'meta_description', 'type' => 'string'],
        ]));

        file_put_contents($this->basePath('resources/fields/summary.json'), json_encode([
            'type' => 'string',
        ]));

        file_put_contents($this->basePath('streams/pages.json'), json_encode([
            'fields' => '@resources/fields/seo.json',
        ]));

        file_put_contents($this->basePath('streams/notes.json'), json_encode([
            'fields' => [
                'id' => 'slug',
                'summary' => '@resources/fields/summary.json',
            ],
        ]));

        $this->artisan('streams:validate', ['paths' => ['streams/pages.json', 'streams/notes.json']])
            ->expectsOutputToContain('0 invalid')
            ->assertSuccessful();
    }

    public function test_it_warns_about_missing_imports()
    {
        file_put_contents($this->basePath('streams/pages.json'), json_encode([
            'fields' => [
                'id' => 'slug',
                'seo' => '@resources/fields/missing.json',
            ],
        ]));

        $this->artisan('streams:validate', ['paths' => ['streams/pages.json'], '--json' => true])
            ->expectsOutputToContain('Import [@resources/fields/missing.json] does not exist');
    }
}
