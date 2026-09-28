<?php

namespace Streams\Sdk\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Streams\Sdk\Support\EntryManager;
use Streams\Sdk\Support\StreamInspector;
use Streams\Sdk\Mcp\Concerns\WritesToApplication;

#[Name('update-entry')]
#[Description('Update an existing entry. Only the given attributes change; the merged entry is validated with the stream\'s field rules before saving. The entry key cannot be changed.')]
#[IsIdempotent]
class UpdateEntry extends StreamsTool
{
    use WritesToApplication;

    public function __construct(
        protected StreamInspector $inspector,
        protected EntryManager $entries
    ) {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'stream' => $schema->string()->description('The stream ID.')->required(),
            'id' => $schema->union(['string', 'integer'])->description('The entry key.')->required(),
            'attributes' => $schema->object()->description('Field handle => new value.')->required(),
        ];
    }

    protected function respond(Request $request): Response
    {
        $request->validate([
            'stream' => 'required|string',
            'id' => 'required',
            'attributes' => 'required|array',
        ]);

        $stream = $this->inspector->find($request->get('stream'));

        return $this->json($this->inspector->entry(
            $this->entries->update($stream, $request->get('id'), $request->get('attributes'))
        ));
    }
}
