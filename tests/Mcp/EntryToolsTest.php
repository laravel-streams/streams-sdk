<?php

namespace Streams\Sdk\Tests\Mcp;

use Streams\Sdk\Mcp\StreamsServer;
use Streams\Sdk\Mcp\Tools\ReadEntry;
use Streams\Sdk\Mcp\Tools\CreateEntry;
use Streams\Sdk\Mcp\Tools\DeleteEntry;
use Streams\Sdk\Mcp\Tools\ListEntries;
use Streams\Sdk\Mcp\Tools\UpdateEntry;

class EntryToolsTest extends McpTestCase
{
    public function test_list_entries()
    {
        $result = $this->decode(
            StreamsServer::tool(ListEntries::class, ['stream' => 'posts'])->assertOk()
        );

        $this->assertSame('posts', $result['stream']);
        $this->assertSame(3, $result['total']);
        $this->assertCount(3, $result['entries']);
    }

    public function test_list_entries_with_constraints_ordering_and_pagination()
    {
        $result = $this->decode(StreamsServer::tool(ListEntries::class, [
            'stream' => 'posts',
            'where' => [['field' => 'status', 'value' => 'live']],
            'order_by' => 'views',
            'direction' => 'desc',
            'limit' => 1,
        ])->assertOk());

        $this->assertSame(2, $result['total']);
        $this->assertSame(2, $result['last_page']);
        $this->assertSame(['third-post'], array_column($result['entries'], 'id'));
    }

    public function test_list_entries_hides_protected_fields()
    {
        $result = $this->decode(StreamsServer::tool(ListEntries::class, [
            'stream' => 'posts',
            'where' => [['field' => 'id', 'value' => 'hello-world']],
        ])->assertOk());

        $this->assertArrayNotHasKey('secret', $result['entries'][0]);
    }

    public function test_read_entry()
    {
        $entry = $this->decode(StreamsServer::tool(ReadEntry::class, [
            'stream' => 'posts',
            'id' => 'hello-world',
        ])->assertOk());

        $this->assertSame('Hello World', $entry['title']);
        $this->assertArrayNotHasKey('secret', $entry);
    }

    public function test_read_missing_entry()
    {
        StreamsServer::tool(ReadEntry::class, ['stream' => 'posts', 'id' => 'missing'])
            ->assertHasErrors(['Entry [missing] was not found in stream [posts].']);
    }

    public function test_create_entry()
    {
        $entry = $this->decode(StreamsServer::tool(CreateEntry::class, [
            'stream' => 'posts',
            'attributes' => [
                'id' => 'new-post',
                'title' => 'New Post',
                'status' => 'draft',
            ],
        ])->assertOk());

        $this->assertSame('new-post', $entry['id']);

        $stored = json_decode(file_get_contents($this->basePath('streams/data/posts/new-post.json')), true);

        $this->assertSame('New Post', $stored['title']);
    }

    public function test_create_entry_validates_attributes()
    {
        StreamsServer::tool(CreateEntry::class, [
            'stream' => 'posts',
            'attributes' => [
                'id' => 'Not A Slug',
                'status' => 'archived',
            ],
        ])->assertHasErrors(['title', 'status']);

        $this->assertFileDoesNotExist($this->basePath('streams/data/posts/Not A Slug.json'));
    }

    public function test_create_entry_rejects_existing_keys()
    {
        StreamsServer::tool(CreateEntry::class, [
            'stream' => 'posts',
            'attributes' => ['id' => 'hello-world', 'title' => 'Duplicate'],
        ])->assertHasErrors(['already exists']);
    }

    public function test_update_entry()
    {
        $entry = $this->decode(StreamsServer::tool(UpdateEntry::class, [
            'stream' => 'posts',
            'id' => 'second-post',
            'attributes' => ['status' => 'live'],
        ])->assertOk());

        $this->assertSame('live', $entry['status']);
        $this->assertSame('Second Post', $entry['title']);

        $stored = json_decode(file_get_contents($this->basePath('streams/data/posts/second-post.json')), true);

        $this->assertSame('live', $stored['status']);
        $this->assertSame('Second Post', $stored['title']);
    }

    public function test_update_entry_validates_merged_attributes()
    {
        StreamsServer::tool(UpdateEntry::class, [
            'stream' => 'posts',
            'id' => 'second-post',
            'attributes' => ['title' => str_repeat('x', 200)],
        ])->assertHasErrors(['title']);
    }

    public function test_update_entry_cannot_change_the_key()
    {
        StreamsServer::tool(UpdateEntry::class, [
            'stream' => 'posts',
            'id' => 'second-post',
            'attributes' => ['id' => 'renamed'],
        ])->assertHasErrors(['Changing the entry key is not supported.']);
    }

    public function test_delete_entry()
    {
        StreamsServer::tool(DeleteEntry::class, [
            'stream' => 'posts',
            'id' => 'third-post',
        ])->assertOk();

        $this->assertFileDoesNotExist($this->basePath('streams/data/posts/third-post.json'));
    }
}
