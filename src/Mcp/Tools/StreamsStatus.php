<?php

namespace Streams\Sdk\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Streams\Sdk\Mcp\Tool;
use Streams\Sdk\Support\ProjectStatus;

/**
 * Call this first. It is the answer to "what is this app and what do I do
 * next?", which is otherwise an agent's most expensive guess.
 */
#[Name('streams-status')]
#[Title('Project status and next steps')]
#[Description('Start here. Reports the Laravel and PHP versions, which Streams packages are installed and at what version, how many streams are registered versus how many definition files exist (and which ones disagree), whether generated schemas exist, whether this MCP server is registered and read-only, and an ordered next-steps list with the exact command for each step. Takes no arguments.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class StreamsStatus extends Tool
{
    public function handle(): Response|ResponseFactory
    {
        return $this->data((new ProjectStatus(app()))->toArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
