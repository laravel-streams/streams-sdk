<?php

namespace Streams\Sdk\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Streams\Sdk\Support\StreamInspector;

#[Name('stream')]
#[MimeType('application/json')]
#[Description('A registered stream\'s description: source, fields, resolved rules, routes, and original definition. Same output as the describe-stream tool.')]
class StreamResource extends Resource implements HasUriTemplate
{
    public function __construct(protected StreamInspector $inspector)
    {
    }

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('streams://streams/{id}');
    }

    public function handle(Request $request): Response
    {
        try {
            $stream = $this->inspector->find((string) $request->get('id'));
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        return Response::text(json_encode(
            $this->inspector->describe($stream),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
    }
}
