<?php

namespace Streams\Sdk\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Attributes\Instructions;

#[Name('Streams')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Local development tools for a Laravel app built on Laravel Streams (streams/core).

    Streams models the app's domain as "streams": JSON definitions in streams/{id}.json that declare fields, validation, a data source (flat files by default, or database/Eloquent), and routes. Entries are the records in a stream.

    - Discover: list-streams, then describe-stream or the streams://streams/{id} resource.
    - Data: list-entries, read-entry, create-entry, update-entry, delete-entry. Writes are validated with the stream's field rules.
    - Definitions: definition-schema (JSON Schema for streams/*.json), validate-stream-definition, make-stream. Validate every definition you write or edit.
    - Entry shape: entry-schema.
    - Docs: search-docs, then read-doc. Prefer the docs over guessing config keys.
    - Scaffolding: make-addon.

    Flat-file entries live in the working tree (streams/data by default), so writes show up in git.
    MARKDOWN)]
class StreamsServer extends Server
{
    protected array $tools = [
        Tools\ListStreams::class,
        Tools\DescribeStream::class,
        Tools\EntrySchema::class,
        Tools\DefinitionSchema::class,
        Tools\ValidateStreamDefinition::class,
        Tools\ListEntries::class,
        Tools\ReadEntry::class,
        Tools\CreateEntry::class,
        Tools\UpdateEntry::class,
        Tools\DeleteEntry::class,
        Tools\SearchDocs::class,
        Tools\ReadDoc::class,
        Tools\MakeStream::class,
        Tools\MakeAddon::class,
    ];

    protected array $resources = [
        Resources\DefinitionSchemaResource::class,
        Resources\StreamResource::class,
    ];

    protected array $prompts = [
        Prompts\DesignStream::class,
    ];
}
