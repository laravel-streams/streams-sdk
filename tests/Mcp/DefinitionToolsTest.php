<?php

namespace Streams\Sdk\Tests\Mcp;

use Streams\Sdk\Mcp\StreamsServer;
use Streams\Sdk\Mcp\Tools\MakeAddon;
use Streams\Sdk\Mcp\Tools\MakeStream;
use Streams\Sdk\Mcp\Tools\CreateEntry;
use Streams\Sdk\Mcp\Tools\ValidateStreamDefinition;

class DefinitionToolsTest extends McpTestCase
{
    public function test_validate_a_valid_definition()
    {
        $result = $this->decode(StreamsServer::tool(ValidateStreamDefinition::class, [
            'id' => 'tags',
            'definition' => [
                'name' => 'Tags',
                'fields' => [
                    ['handle' => 'id', 'type' => 'slug', 'required' => true],
                    ['handle' => 'post_id', 'type' => 'relationship', 'config' => ['related' => 'posts']],
                ],
            ],
        ])->assertOk());

        $this->assertTrue($result['valid'], json_encode($result));
        $this->assertSame([], $result['errors']);
    }

    public function test_validate_reports_unregistered_field_types()
    {
        $result = $this->decode(StreamsServer::tool(ValidateStreamDefinition::class, [
            'definition' => [
                'fields' => [['handle' => 'body', 'type' => 'wysiwyg']],
            ],
        ])->assertOk());

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Field type [wysiwyg] is not registered', implode("\n", $result['errors']));
    }

    public function test_validate_reports_misplaced_related_config()
    {
        $result = $this->decode(StreamsServer::tool(ValidateStreamDefinition::class, [
            'definition' => [
                'fields' => [['handle' => 'author_id', 'type' => 'relationship', 'related' => 'authors']],
            ],
        ])->assertOk());

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('"related" must be inside "config"', implode("\n", $result['errors']));
    }

    public function test_validate_reports_schema_errors()
    {
        $result = $this->decode(StreamsServer::tool(ValidateStreamDefinition::class, [
            'definition' => [
                'name' => ['not', 'a', 'string'],
                'config' => ['source' => ['format' => 'markdown']],
            ],
        ])->assertOk());

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('/name', implode("\n", $result['errors']));
        $this->assertStringContainsString('/config/source/format', implode("\n", $result['errors']));
    }

    public function test_validate_warns_about_unregistered_related_streams()
    {
        $result = $this->decode(StreamsServer::tool(ValidateStreamDefinition::class, [
            'definition' => [
                'fields' => [['handle' => 'tag_id', 'type' => 'relationship', 'config' => ['related' => 'tags']]],
            ],
        ])->assertOk());

        $this->assertTrue($result['valid']);
        $this->assertStringContainsString('Stream [tags] is not registered yet', implode("\n", $result['warnings']));
    }

    public function test_validate_warns_once_per_related_stream_in_a_list()
    {
        $result = $this->decode(StreamsServer::tool(ValidateStreamDefinition::class, [
            'id' => 'comments',
            'definition' => [
                'fields' => [[
                    'handle' => 'commentable',
                    'type' => 'polymorphic',
                    'config' => ['related' => ['posts', 'pages', 'comments']],
                ]],
            ],
        ])->assertOk());

        $warnings = implode("\n", $result['warnings']);

        $this->assertTrue($result['valid'], json_encode($result));
        $this->assertStringNotContainsString('Stream [posts]', $warnings);
        $this->assertStringNotContainsString('Stream [comments]', $warnings);
        $this->assertStringContainsString('Stream [pages] is not registered yet', $warnings);
    }

    public function test_validate_warns_when_default_is_not_in_config()
    {
        $result = $this->decode(StreamsServer::tool(ValidateStreamDefinition::class, [
            'definition' => [
                'fields' => [['handle' => 'title', 'type' => 'string', 'default' => 'Untitled']],
            ],
        ])->assertOk());

        $this->assertTrue($result['valid'], json_encode($result));
        $this->assertStringContainsString('config.default', implode("\n", $result['warnings']));
    }

    public function test_validate_existing_files()
    {
        $result = $this->decode(
            StreamsServer::tool(ValidateStreamDefinition::class, ['id' => 'posts'])->assertOk()
        );

        $this->assertTrue($result['valid']);

        $all = $this->decode(StreamsServer::tool(ValidateStreamDefinition::class)->assertOk());

        $this->assertSame(['streams/authors.json', 'streams/posts.json'], array_keys($all));
    }

    public function test_make_stream_writes_and_registers_the_definition()
    {
        $result = $this->decode(StreamsServer::tool(MakeStream::class, [
            'id' => 'tags',
            'description' => 'Post tags.',
            'fields' => [
                ['handle' => 'id', 'type' => 'slug', 'required' => true],
                ['handle' => 'label', 'type' => 'string', 'required' => true],
            ],
        ])->assertOk());

        $this->assertSame('streams/tags.json', $result['created']);

        $definition = json_decode(file_get_contents($this->basePath('streams/tags.json')), true);

        $this->assertSame('Tags', $definition['name']);
        $this->assertSame('https://streams.dev/schema/streams.schema.json', $definition['$schema']);

        // The new stream is usable right away.
        StreamsServer::tool(CreateEntry::class, [
            'stream' => 'tags',
            'attributes' => ['id' => 'php', 'label' => 'PHP'],
        ])->assertOk();

        $this->assertFileExists($this->basePath('streams/data/tags/php.json'));
    }

    public function test_make_stream_defaults_to_a_uuid_key()
    {
        $result = $this->decode(StreamsServer::tool(MakeStream::class, ['id' => 'notes'])->assertOk());

        $this->assertSame('uuid', $result['definition']['fields'][0]['type']);

        $entry = $this->decode(StreamsServer::tool(CreateEntry::class, [
            'stream' => 'notes',
            'attributes' => [],
        ])->assertOk());

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $entry['id']);
    }

    public function test_make_stream_refuses_to_overwrite()
    {
        StreamsServer::tool(MakeStream::class, ['id' => 'posts'])
            ->assertHasErrors(['already exists']);

        $this->assertStringContainsString('Blog posts.', file_get_contents($this->basePath('streams/posts.json')));
    }

    public function test_make_stream_does_not_write_invalid_definitions()
    {
        StreamsServer::tool(MakeStream::class, [
            'id' => 'broken',
            'fields' => [['handle' => 'body', 'type' => 'wysiwyg']],
        ])->assertHasErrors(['Field type [wysiwyg] is not registered']);

        $this->assertFileDoesNotExist($this->basePath('streams/broken.json'));
    }

    public function test_make_stream_requires_snake_case_ids()
    {
        StreamsServer::tool(MakeStream::class, ['id' => '../escape'])
            ->assertHasErrors(['must be snake_case']);
    }

    public function test_make_addon()
    {
        $result = $this->decode(StreamsServer::tool(MakeAddon::class, [
            'name' => 'acme/widgets',
            'description' => 'Widgets.',
        ])->assertOk());

        $this->assertSame('Acme\\Widgets\\WidgetsProvider', $result['provider']);
        $this->assertFileExists($this->basePath('addons/acme/widgets/composer.json'));

        StreamsServer::tool(MakeAddon::class, ['name' => 'acme/widgets'])
            ->assertHasErrors(['already exists']);
    }

    public function test_make_addon_rejects_invalid_names()
    {
        StreamsServer::tool(MakeAddon::class, ['name' => 'Bad Name'])
            ->assertHasErrors(['is not a valid']);
    }
}
