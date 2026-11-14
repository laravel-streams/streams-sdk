<?php

namespace Streams\Sdk\Mcp\Concerns;

/**
 * Hides a write tool entirely when the app is configured read-only, instead of
 * registering it and refusing calls: a tool an agent cannot see, it will not
 * try. Set STREAMS_MCP_READ_ONLY=true to look without touching.
 */
trait IsWriteTool
{
    public function shouldRegister(): bool
    {
        return ! (bool) config('streams.sdk.mcp.read_only', false);
    }
}
