<?php

namespace Streams\Sdk\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Streams\Sdk\Support\StreamInspector;

#[Name('describe-stream')]
#[Description('Describe a registered stream: source, key name, routes, the definition file path, the original definition, and every field with its type, resolved validation rules, and config. Use before reading or writing entries.')]
#[IsReadOnly]
class DescribeStream extends StreamsTool
{
    public function __construct(protected StreamInspector $inspector)
    {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'stream' => $schema->string()->description('The stream ID, for example "posts".')->required(),
        ];
    }

    protected function respond(Request $request): Response
    {
        $request->validate(['stream' => 'required|string']);

        return $this->json($this->inspector->describe(
            $this->inspector->find($request->get('stream'))
        ));
    }
}
