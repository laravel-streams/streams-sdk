<?php

/**
 * streams/sdk configuration.
 *
 * Defaults are merged under the `streams.sdk` key by SdkServiceProvider. Publish
 * this file to config/streams/sdk.php to override it; Laravel loads that path as
 * `streams.sdk`, so the keys below line up one for one.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | MCP server (agents)
    |--------------------------------------------------------------------------
    |
    | The SDK registers a local-development Model Context Protocol server so AI
    | agents can read your streams, check the definitions they write, and run
    | the generators instead of guessing from source files. It needs laravel/mcp
    | and runs over stdio: `php artisan mcp:start streams`. It is never
    | registered in production unless you opt in.
    |
    */

    'mcp' => [

        // Register the server at all. Set false to hide Streams from every agent.
        'enabled' => env('STREAMS_MCP_ENABLED', true),

        // Also register when APP_ENV=production. Leave false: the server acts
        // with your app's full permissions, including writes to your data.
        'allow_production' => env('STREAMS_MCP_ALLOW_PRODUCTION', false),

        // The handle passed to `php artisan mcp:start {handle}`.
        'handle' => env('STREAMS_MCP_HANDLE', 'streams'),

        // Hide every write tool (create-entry, update-entry, delete-entry,
        // make-stream, make-addon). Agents keep read and validate access only.
        'read_only' => env('STREAMS_MCP_READ_ONLY', false),

        'docs' => [

            // Where `search-docs` and `read-doc` look, relative to the app root.
            // The index is built from local files only: no network calls.
            'paths' => [
                'docs',
                'vendor/streams/*/docs',
                'streams/data/*docs',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Where definitions live
    |--------------------------------------------------------------------------
    |
    | The one directory stream definitions are read from and written to,
    | relative to the app root. Core loads streams/ at boot; the SDK writes
    | there too, so `make-stream` and hand-editing cannot diverge.
    |
    */

    'definitions' => [
        'path' => env('STREAMS_DEFINITIONS_PATH', 'streams'),
    ],
];

