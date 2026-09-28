<?php

namespace Streams\Sdk\Mcp\Concerns;

use Illuminate\Support\Facades\Config;

/**
 * Tools that write entries or files are hidden
 * when streams.sdk.mcp.read_only is enabled.
 */
trait WritesToApplication
{
    public function shouldRegister(): bool
    {
        return !Config::get('streams.sdk.mcp.read_only', false);
    }
}
