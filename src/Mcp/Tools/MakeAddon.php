<?php

namespace Streams\Sdk\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Description;
use Streams\Sdk\Support\AddonGenerator;
use Streams\Sdk\Mcp\Concerns\WritesToApplication;

#[Name('make-addon')]
#[Description('Scaffold a Streams addon package (composer.json and a service provider) in addons/{vendor}/{name}. Add it to the app with a Composer path repository. The MCP equivalent of `php artisan make:addon`.')]
class MakeAddon extends StreamsTool
{
    use WritesToApplication;

    public function __construct(protected AddonGenerator $generator)
    {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Composer package name, for example "acme/widgets".')->required(),
            'description' => $schema->string()->description('A short package description.'),
            'overwrite' => $schema->boolean()->description('Replace an existing addon scaffold. Defaults to false.'),
        ];
    }

    protected function respond(Request $request): Response
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'overwrite' => 'nullable|boolean',
        ]);

        return $this->json($this->generator->make(
            $request->get('name'),
            $request->get('description') ?: 'An addon for Laravel Streams.',
            (bool) $request->get('overwrite', false)
        ));
    }
}
