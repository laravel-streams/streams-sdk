<?php

namespace Streams\Sdk\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Streams\Sdk\Support\StreamDefinitionValidator;

#[Name('validate-stream-definition')]
#[Description('Validate a stream definition before or after writing it. Checks the JSON Schema, then the running app: registered field types, related and extended streams, model and adapter classes, and a test build by streams/core. Pass "definition" to check unsaved JSON, "id" to check streams/{id}.json, or nothing to check every streams/*.json file. Same checks as `php artisan streams:validate`.')]
#[IsReadOnly]
class ValidateStreamDefinition extends StreamsTool
{
    public function __construct(protected StreamDefinitionValidator $validator)
    {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'definition' => $schema->object()->description('A stream definition to validate, as it would appear in streams/{id}.json.'),
            'id' => $schema->string()->description('The stream ID. With "definition", used as the ID when the definition has none. Alone, validates streams/{id}.json.'),
        ];
    }

    protected function respond(Request $request): Response
    {
        $request->validate([
            'definition' => 'nullable|array',
            'id' => 'nullable|string',
        ]);

        $id = $request->get('id');

        if ($definition = $request->get('definition')) {
            return $this->json($this->validator->validate($definition, $id));
        }

        if ($id) {
            if (!preg_match('/^[A-Za-z0-9_.-]+$/', $id)) {
                return Response::error("Invalid stream ID [{$id}].");
            }

            return $this->json($this->validator->validateFile(base_path("streams/{$id}.json")));
        }

        $results = [];

        foreach (File::glob(base_path('streams/*.json')) as $path) {
            $results['streams/' . basename($path)] = $this->validator->validateFile($path);
        }

        return $this->json($results);
    }
}
