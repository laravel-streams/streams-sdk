<?php

namespace Streams\Sdk\Tests\Mcp;

use Streams\Sdk\Mcp\StreamsServer;
use Streams\Sdk\Mcp\Tools\ReadDoc;
use Streams\Sdk\Mcp\Tools\SearchDocs;
use Streams\Sdk\Mcp\Prompts\DesignStream;

class DocsToolsTest extends McpTestCase
{
    public function test_search_docs_ranks_matches()
    {
        $result = $this->decode(StreamsServer::tool(SearchDocs::class, [
            'query' => 'relationship related',
        ])->assertOk());

        $this->assertSame('docs/relationships.md', $result['results'][0]['id']);
        $this->assertSame('Relationship Fields', $result['results'][0]['title']);
        $this->assertStringContainsString('config.related', $result['results'][0]['snippet']);
    }

    public function test_search_docs_without_matches()
    {
        $result = $this->decode(StreamsServer::tool(SearchDocs::class, [
            'query' => 'zyxwvut',
        ])->assertOk());

        $this->assertSame([], $result['results']);
        $this->assertArrayHasKey('hint', $result);
    }

    public function test_read_doc()
    {
        $response = StreamsServer::tool(ReadDoc::class, ['id' => 'docs/sources.md'])->assertOk();

        $this->assertStringContainsString('# Sources', $this->text($response));
        $this->assertStringContainsString('filebase source', $this->text($response));
    }

    public function test_read_doc_only_reads_indexed_documents()
    {
        StreamsServer::tool(ReadDoc::class, ['id' => '../../../../etc/passwd'])
            ->assertHasErrors(['was not found']);

        StreamsServer::tool(ReadDoc::class, ['id' => 'composer.json'])
            ->assertHasErrors(['was not found']);
    }

    public function test_design_stream_prompt()
    {
        StreamsServer::prompt(DesignStream::class, [
            'requirements' => 'Events with a start date and a venue.',
            'id' => 'events',
        ])
            ->assertOk()
            ->assertSee(['Events with a start date and a venue.', '`events`', 'validate-stream-definition']);
    }
}
