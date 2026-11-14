<?php

namespace Streams\Sdk\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;
use Streams\Sdk\Support\StreamFacts;

/**
 * One stream, as the app sees it: `streams://streams/{id}`.
 */
#[Name('stream-definition')]
#[Title('Stream definition')]
#[Description('One registered stream as the app resolved it — source, key, fields with their rules, routes, and the definition file on disk. Example: streams://streams/people.')]
#[MimeType('application/json')]
class StreamDefinition extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('streams://streams/{id}');
    }

    public function handle(Request $request): Response
    {
        $id = (string) $request->get('id');

        try {
            if (! \Streams\Core\Support\Facades\Streams::exists($id)) {
                return Response::error('Stream ['.$id.'] is not registered. Call list-streams for what exists.');
            }

            return Response::text((string) json_encode(
                StreamFacts::describe(\Streams\Core\Support\Facades\Streams::make($id)),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));
        } catch (\Throwable $e) {
            return Response::error('Stream ['.$id.'] could not be read: '.$e->getMessage());
        }
    }
}
