<?php

namespace Streams\Sdk\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Version;
use Streams\Sdk\Mcp\Prompts\DesignStream;
use Streams\Sdk\Mcp\Resources\StreamDefinition;
use Streams\Sdk\Mcp\Resources\StreamDefinitionSchema;
use Streams\Sdk\Mcp\Tools\CreateEntry;
use Streams\Sdk\Mcp\Tools\DefinitionSchema;
use Streams\Sdk\Mcp\Tools\DeleteEntry;
use Streams\Sdk\Mcp\Tools\DescribeStream;
use Streams\Sdk\Mcp\Tools\EntrySchema;
use Streams\Sdk\Mcp\Tools\HowTo;
use Streams\Sdk\Mcp\Tools\ListEntries;
use Streams\Sdk\Mcp\Tools\ListStreams;
use Streams\Sdk\Mcp\Tools\MakeAddon;
use Streams\Sdk\Mcp\Tools\MakeStream;
use Streams\Sdk\Mcp\Tools\ReadDoc;
use Streams\Sdk\Mcp\Tools\ReadEntry;
use Streams\Sdk\Mcp\Tools\SearchDocs;
use Streams\Sdk\Mcp\Tools\StreamsStatus;
use Streams\Sdk\Mcp\Tools\UpdateEntry;
use Streams\Sdk\Mcp\Tools\ValidateStreamDefinition;

/**
 * The agent's door into a Streams app, and the human door to the same facts
 * (`php artisan streams:tui` reads the same support classes).
 *
 * Started over stdio by the agent client: `php artisan mcp:start streams`.
 * Registration happens in SdkServiceProvider, and only outside production.
 */
#[Name('streams')]
#[Title('Streams')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Streams (laravel-streams) models data as JSON: a stream in streams/{id}.json declares fields, their rules, and a source (flat files, a database table, an Eloquent model, a collection). The same criteria query works on every source, so storage can change without changing your code. SALLT is the recommended stack: Streams, Alpine, Laravel, Livewire, Tailwind.

    Work from the model out, and let the types do the arguing. Thin inference is where agents go wrong, so ask the app rather than guessing from file names: describe-stream gives you fields with the rules Core will apply, entry-schema gives you the shape of valid entries, definition-schema validates the definition you are about to write.

    Start with streams-status: what is installed, what is registered, what to run next. Then:
    - describe-stream {stream} before you read or write entries. Never invent a field handle.
    - list-entries / read-entry to see real data; create-entry / update-entry to write, which validate first and refuse unknown or protected fields.
    - make-stream {id} to write a new definition; it refuses invalid JSON and registers what it writes. Edit that file by hand when you know what you want, then run validate-stream-definition.
    - how-to {recipe} for the steps of a job you have not done here before (add a field, an admin panel, a REST API, typed generation).
    - search-docs / read-doc search the docs installed in this app: the version you are running, not whatever you remember. streams.dev/llms.txt is the published index if a page is missing here.

    Rules: the definition file is the source of truth, so edit it rather than patching generated code. After any definition change run validate-stream-definition, then regenerate types (`php artisan streams:schema`). Do not delete definitions or entries you did not create. If a step you need has no tool and no command, say so instead of improvising around it.
    MARKDOWN)]
class StreamsServer extends Server
{
    protected array $tools = [
        StreamsStatus::class,
        HowTo::class,
        ListStreams::class,
        DescribeStream::class,
        DefinitionSchema::class,
        EntrySchema::class,
        ValidateStreamDefinition::class,
        ListEntries::class,
        ReadEntry::class,
        SearchDocs::class,
        ReadDoc::class,
        CreateEntry::class,
        UpdateEntry::class,
        DeleteEntry::class,
        MakeStream::class,
        MakeAddon::class,
    ];

    protected array $resources = [
        StreamDefinitionSchema::class,
        StreamDefinition::class,
    ];

    protected array $prompts = [
        DesignStream::class,
    ];
}
