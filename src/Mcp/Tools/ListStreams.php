<?php

namespace Streams\Sdk\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Streams\Sdk\Support\StreamInspector;

#[Name('list-streams')]
#[Description('List every registered stream (the app\'s domain models) with its ID, name, description, source type, and field handles. Equivalent to `php artisan streams:list`. Start here to discover what the application models.')]
#[IsReadOnly]
class ListStreams extends StreamsTool
{
    public function __construct(protected StreamInspector $inspector)
    {
    }

    protected function respond(Request $request): Response
    {
        return $this->json($this->inspector->summaries());
    }
}
