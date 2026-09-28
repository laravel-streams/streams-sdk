<?php

namespace Streams\Sdk\Tests\Mcp;

use Streams\Sdk\Mcp\StreamsServer;
use Streams\Sdk\Mcp\Tools\EntrySchema;
use Streams\Sdk\Mcp\Tools\ListStreams;
use Streams\Sdk\Mcp\Tools\DescribeStream;
use Streams\Sdk\Mcp\Tools\DefinitionSchema;
use Streams\Sdk\Mcp\Resources\StreamResource;
use Streams\Sdk\Mcp\Resources\DefinitionSchemaResource;

class StreamToolsTest extends McpTestCase
{
    public function test_list_streams()
    {
        $response = StreamsServer::tool(ListStreams::class)->assertOk();

        $streams = collect($this->decode($response))->keyBy('id');

        $this->assertSame('Posts', $streams['posts']['name']);
        $this->assertSame('filebase', $streams['posts']['source']);
        $this->assertSame(['id', 'title', 'status', 'author_id', 'views', 'secret'], $streams['posts']['fields']);
    }

    public function test_describe_stream()
    {
        $response = StreamsServer::tool(DescribeStream::class, ['stream' => 'posts'])->assertOk();

        $stream = $this->decode($response);
        $fields = collect($stream['fields'])->keyBy('handle');

        $this->assertSame('streams/posts.json', $stream['definition_file']);
        $this->assertSame('id', $stream['key_name']);
        $this->assertSame('filebase', $stream['source']['type']);
        $this->assertTrue($fields['title']['required']);
        $this->assertContains('max:120', $fields['title']['rules']);
        $this->assertSame('authors', $fields['author_id']['config']['related']);
        $this->assertTrue($fields['secret']['protected']);
        $this->assertArrayNotHasKey('$schema', $stream['definition']);
    }

    public function test_describe_unknown_stream()
    {
        StreamsServer::tool(DescribeStream::class, ['stream' => 'nope'])
            ->assertHasErrors(['Stream [nope] is not registered.']);
    }

    public function test_describe_requires_a_stream()
    {
        StreamsServer::tool(DescribeStream::class, [])
            ->assertHasErrors(['stream']);
    }

    public function test_entry_schema()
    {
        $schema = $this->decode(
            StreamsServer::tool(EntrySchema::class, ['stream' => 'posts'])->assertOk()
        );

        $this->assertSame('object', $schema['type']);
        $this->assertSame(['id', 'title'], $schema['required']);
        $this->assertSame(['draft', 'live'], $schema['properties']['status']['enum']);
    }

    public function test_definition_schema()
    {
        $schema = $this->decode(StreamsServer::tool(DefinitionSchema::class)->assertOk());

        $this->assertSame('https://streams.dev/schema/streams.schema.json', $schema['$id']);
        $this->assertArrayHasKey('fields', $schema['properties']);
    }

    public function test_stream_resource()
    {
        $response = StreamsServer::resource(StreamResource::class, ['id' => 'authors'])->assertOk();

        $this->assertSame('authors', json_decode($this->text($response), true)['id']);
    }

    public function test_definition_schema_resource()
    {
        $response = StreamsServer::resource(DefinitionSchemaResource::class)->assertOk();

        $this->assertSame('Streams stream definition', json_decode($this->text($response), true)['title']);
    }
}
