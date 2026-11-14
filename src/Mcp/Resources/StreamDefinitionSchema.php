<?php

namespace Streams\Sdk\Mcp\Resources;

use Illuminate\Support\Facades\File;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;
use Streams\Sdk\Support\DefinitionWriter;

/**
 * The JSON Schema for streams/*.json. The same document a `$schema` key points
 * at, so an editor and this server agree on what a valid definition is.
 */
#[Name('stream-definition-schema')]
#[Title('Stream definition schema')]
#[Description('The JSON Schema every streams/*.json definition file follows — the same document served at https://streams.dev/schema/streams.schema.json and pointed at by the "$schema" key in each file.')]
#[Uri('streams://schemas/streams.schema.json')]
#[MimeType('application/json')]
class StreamDefinitionSchema extends Resource
{
    public function handle(): Response
    {
        $path = DefinitionWriter::schemaPath();

        if (! is_file($path)) {
            return Response::error('The stream definition schema is missing from the SDK install ('.basename($path).').');
        }

        return Response::text((string) file_get_contents($path));
    }
}
