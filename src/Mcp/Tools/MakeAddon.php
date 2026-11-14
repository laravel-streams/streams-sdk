<?php

namespace Streams\Sdk\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Streams\Sdk\Console\Commands\MakeAddon as MakeAddonCommand;
use Streams\Sdk\Mcp\Concerns\IsWriteTool;
use Streams\Sdk\Mcp\Tool;
use Streams\Sdk\Support\StreamFacts;
use Streams\Sdk\Support\WriteFailure;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Runs the same `make:addon` command a person would run, non-interactively, and
 * reports what it wrote. One implementation, two surfaces.
 */
#[Name('make-addon')]
#[Title('Scaffold an addon package')]
#[Description('Scaffold a Streams addon at addons/{vendor}/{name}/ with its own composer.json and service provider — the shape an extension takes when it ships stream definitions, views, or panel sections for other apps to install. Pass {"name": "acme/invoices", "description": "Invoice streams for Acme."}. Runs the same `php artisan make:addon` code, so nothing here is a second, weaker generator. This does not install or enable the addon: say so and stop rather than editing composer.json by hand.')]
#[IsOpenWorld(false)]
class MakeAddon extends Tool
{
    use IsWriteTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Pass a composer-style name, for example {"name": "acme/invoices"}.',
        ]);

        $name = (string) $validated['name'];

        try {
            $output = $this->run($name, isset($validated['description']) ? (string) $validated['description'] : null);
        } catch (Throwable $e) {
            return $this->failure($e);
        }

        [$vendor, $package] = explode('/', $name, 2);

        return $this->data([
            'created' => true,
            'name' => $name,
            'path' => 'addons/'.$vendor.'/'.$package,
            'files' => $this->written($vendor, $package),
            'output' => trim($output),
            'next' => [
                'Register the provider (or rely on package discovery) before its streams load.',
                'Add streams to addons/'.$vendor.'/'.$package.'/streams/ and run validate-stream-definition.',
            ],
        ]);
    }

    /**
     * Drive the Artisan command in-process. A non-interactive input makes the
     * command's one question fall back to its default, which is what a
     * description argument is for.
     */
    protected function run(string $name, ?string $description): string
    {
        $command = new MakeAddonCommand();
        $command->setLaravel(app());

        $output = new BufferedOutput();

        $status = $command->run(new ArrayInput(['name' => $name]), $output);

        if ($status !== 0) {
            throw new WriteFailure('make:addon exited with status '.$status.': '.trim($output->fetch()));
        }

        return $output->fetch();
    }

    /**
     * @return array<int, string>
     */
    protected function written(string $vendor, string $package): array
    {
        $directory = base_path('addons/'.$vendor.'/'.$package);

        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        $finder = Finder::create()
            ->files()
            ->in($directory)
            ->ignoreUnreadableDirs()
            ->ignoreDotFiles(true);

        foreach ($finder as $file) {
            $files[] = StreamFacts::relative((string) $file->getRealPath());
        }

        sort($files);

        return $files;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Composer package name, "vendor/package" — e.g. "acme/invoices".')->required(),
            'description' => $schema->string()->description('One line for the addon\'s composer.json.'),
        ];
    }
}
