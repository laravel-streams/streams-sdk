<?php

namespace Streams\Sdk\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Description;
use Streams\Sdk\Support\StreamDefinitionValidator;

#[Name('stream-definition-schema')]
#[Uri('streams://schemas/streams.schema.json')]
#[MimeType('application/schema+json')]
#[Description('JSON Schema (draft-07) for stream definition files, streams/{id}.json.')]
class DefinitionSchemaResource extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::text((string) file_get_contents(StreamDefinitionValidator::schemaPath()));
    }
}
