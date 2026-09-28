<?php

namespace Streams\Sdk\Tests\Mcp;

use Laravel\Mcp\Facades\Mcp;
use Streams\Sdk\Mcp\StreamsServer;

class StreamsServerTest extends McpTestCase
{
    public function test_it_registers_a_local_server()
    {
        $this->assertNotNull(Mcp::getLocalServer('streams'));
    }

    public function test_it_lists_every_tool()
    {
        $this->assertSame([
            'list-streams',
            'describe-stream',
            'entry-schema',
            'definition-schema',
            'validate-stream-definition',
            'list-entries',
            'read-entry',
            'create-entry',
            'update-entry',
            'delete-entry',
            'search-docs',
            'read-doc',
            'make-stream',
            'make-addon',
        ], $this->names(StreamsServer::tools()));
    }

    public function test_read_only_mode_hides_write_tools()
    {
        config(['streams.sdk.mcp.read_only' => true]);

        $names = $this->names(StreamsServer::tools());

        $this->assertContains('list-entries', $names);

        foreach (['create-entry', 'update-entry', 'delete-entry', 'make-stream', 'make-addon'] as $tool) {
            $this->assertNotContains($tool, $names);
        }
    }

    public function test_it_lists_resources_and_prompts()
    {
        $this->assertContains('stream-definition-schema', $this->names(StreamsServer::resources()));
        $this->assertSame(['design-stream'], $this->names(StreamsServer::prompts()));
    }
}
