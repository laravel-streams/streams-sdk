<?php

namespace Streams\Sdk\Tests\Mcp;

use Streams\Sdk\Tests\SdkTestCase;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Testing\TestListResponse;

abstract class McpTestCase extends SdkTestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Laravel\Mcp\Server::class)) {
            $this->markTestSkipped('laravel/mcp is not installed.');
        }

        parent::setUp();
    }

    /**
     * Decode the JSON text returned by a Streams tool.
     */
    protected function decode(TestResponse $response): mixed
    {
        $content = (fn () => $this->content())->call($response);

        return json_decode($content[0] ?? 'null', true);
    }

    protected function text(TestResponse $response): string
    {
        return implode("\n", (fn () => $this->content())->call($response));
    }

    protected function names(TestListResponse $response): array
    {
        return array_column((fn () => $this->items)->call($response), 'name');
    }
}
