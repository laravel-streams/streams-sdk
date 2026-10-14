<?php

namespace Streams\Sdk\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Streams\Core\Criteria\Adapter\FilebaseAdapter;
use Streams\Sdk\Support\StreamSourceValidator;
use Streams\Sdk\Tests\SdkTestCase;

/**
 * The streams.dev streams page advertises source examples plus a legacy
 * stream handle, a validation key, and implicit sort/pagination config.
 * Repository applies the source examples. It does not apply the legacy keys.
 */
class StreamSourceValidatorTest extends SdkTestCase
{
    public function test_filebase_database_and_eloquent_sources_match_runtime(): void
    {
        $validator = new StreamSourceValidator;

        $filebase = $validator->validate([
            'id' => 'products',
            'name' => 'Products',
            'fields' => [
                ['handle' => 'title', 'type' => 'string', 'required' => true],
            ],
            'rules' => ['title' => 'required|max:255'],
            'config' => [
                'source' => [
                    'format' => 'json',
                    'path' => 'content/products',
                ],
            ],
        ]);

        $this->assertTrue($filebase['valid'], implode("\n", $filebase['errors']));
        $this->assertSame([], $filebase['warnings']);
        $this->assertSame(config('streams.core.default_source'), $filebase['source_type']);

        $database = $validator->validate([
            'id' => 'products',
            'name' => 'Products',
            'config' => [
                'source' => [
                    'type' => 'database',
                    'table' => 'products',
                ],
            ],
        ]);

        $this->assertTrue($database['valid'], implode("\n", $database['errors']));
        $this->assertSame('database', $database['source_type']);

        $eloquent = $validator->validate([
            'id' => 'products',
            'name' => 'Products',
            'config' => [
                'source' => [
                    'type' => 'eloquent',
                    'model' => Model::class,
                ],
            ],
        ]);

        $this->assertTrue($eloquent['valid'], implode("\n", $eloquent['errors']));
        $this->assertSame('eloquent', $eloquent['source_type']);

        $adapter = $validator->validate([
            'id' => 'products',
            'config' => [
                'source' => [
                    'adapter' => FilebaseAdapter::class,
                ],
            ],
        ]);

        $this->assertTrue($adapter['valid'], implode("\n", $adapter['errors']));
        $this->assertNull($adapter['source_type']);

        $unknown = $validator->validate([
            'id' => 'products',
            'config' => [
                'source' => [
                    'type' => 'not_a_source',
                ],
            ],
        ]);

        $this->assertFalse($unknown['valid']);
        $this->assertStringContainsString('not_a_source', implode("\n", $unknown['errors']));
    }

    public function test_legacy_handle_validation_and_implicit_sort_pagination_are_out_of_scope(): void
    {
        $result = (new StreamSourceValidator)->validate([
            'name' => 'Blog Posts',
            'handle' => 'blog_posts',
            'description' => 'Content management for blog articles',
            'config' => [
                'source' => [
                    'type' => 'eloquent',
                    'model' => Model::class,
                ],
                'pagination' => [
                    'per_page' => 20,
                    'page_name' => 'page',
                ],
                'sort' => [
                    'field' => 'created_at',
                    'direction' => 'desc',
                ],
                'cache' => [
                    'ttl' => 3600,
                    'tags' => ['products', 'catalog'],
                ],
            ],
            'fields' => [
                [
                    'handle' => 'title',
                    'type' => 'string',
                    'required' => true,
                ],
            ],
            'validation' => [
                'title' => 'required',
            ],
        ]);

        $errors = implode("\n", $result['errors']);
        $warnings = implode("\n", $result['warnings']);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('deprecated alias of "id"', $warnings);
        $this->assertStringContainsString('"validation" is not applied', $errors);
        $this->assertStringContainsString('"config.pagination" is not applied', $errors);
        $this->assertStringContainsString('"config.sort" is not applied', $errors);
        $this->assertStringNotContainsString('cache', $errors);
        $this->assertStringNotContainsString('title', $warnings);
    }

    public function test_streams_validate_fails_the_documented_legacy_configuration(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'stream-source-');
        file_put_contents($path, json_encode([
            'name' => 'Blog Posts',
            'handle' => 'blog_posts',
            'config' => [
                'source' => [
                    'type' => 'eloquent',
                    'model' => Model::class,
                ],
                'pagination' => ['per_page' => 20, 'page_name' => 'page'],
                'sort' => ['field' => 'created_at', 'direction' => 'desc'],
            ],
            'validation' => ['title' => 'required'],
            'fields' => [],
        ]));

        try {
            $this->artisan('streams:validate', ['paths' => [$path]])
                ->expectsOutputToContain('"validation" is not applied')
                ->expectsOutputToContain('"config.pagination" is not applied')
                ->expectsOutputToContain('"config.sort" is not applied')
                ->expectsOutputToContain('deprecated alias of "id"')
                ->assertFailed();
        } finally {
            @unlink($path);
        }
    }

    public function test_streams_validate_passes_the_testing_fixtures(): void
    {
        $this->artisan('streams:validate')->assertSuccessful();
    }
}
