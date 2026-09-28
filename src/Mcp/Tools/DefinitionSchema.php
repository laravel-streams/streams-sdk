<?php

namespace Streams\Sdk\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Streams\Sdk\Support\StreamDefinitionValidator;

#[Name('definition-schema')]
#[Description('Get the JSON Schema for stream definition files (streams/{id}.json): top-level keys, config.source options, field shapes, field types, and type-specific config such as config.related and config.options. Read this before writing or editing a stream definition.')]
#[IsReadOnly]
class DefinitionSchema extends StreamsTool
{
    protected function respond(Request $request): Response
    {
        return $this->json(StreamDefinitionValidator::schema());
    }
}
