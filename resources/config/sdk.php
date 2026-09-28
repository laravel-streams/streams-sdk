<?php

return [

    /**
     * The local-development MCP server for AI agents.
     *
     * Requires laravel/mcp (Laravel 11.45+ or 12.41+). Start it with:
     * php artisan mcp:start streams
     */
    'mcp' => [

        /**
         * Register the MCP server. It is never
         * registered in production unless
         * "allow_production" is enabled.
         */
        'enabled' => env('STREAMS_MCP_ENABLED', true),

        'allow_production' => env('STREAMS_MCP_ALLOW_PRODUCTION', false),

        /**
         * The handle passed to "mcp:start".
         */
        'handle' => env('STREAMS_MCP_HANDLE', 'streams'),

        /**
         * Hide tools that write entries or files.
         */
        'read_only' => env('STREAMS_MCP_READ_ONLY', false),

        /**
         * Markdown documentation searched by the "search-docs" tool.
         * Glob patterns relative to the application root.
         */
        'docs' => [
            'paths' => [
                'docs',
                'vendor/streams/*/docs',
                'streams/data/*docs',
            ],
        ],
    ],
];
