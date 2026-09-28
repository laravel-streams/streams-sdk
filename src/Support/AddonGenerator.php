<?php

namespace Streams\Sdk\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Scaffolds a Streams addon package in addons/{vendor}/{name}.
 */
class AddonGenerator
{
    public const NAME_PATTERN = "/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9](([_.]?|-{0,2})[a-z0-9]+)*$/";

    /**
     * @return array{path: string, files: array<int, string>, provider: string}
     */
    public function make(string $package, string $description = 'An addon for Laravel Streams.', bool $overwrite = false): array
    {
        if (!preg_match(static::NAME_PATTERN, $package)) {
            throw new \InvalidArgumentException("The name [{$package}] is not a valid: https://getcomposer.org/doc/04-schema.md#name");
        }

        [$vendor, $name] = explode('/', $package);

        $relative = "addons/{$vendor}/{$name}";
        $path = base_path($relative);

        if (File::exists($path . '/composer.json') && !$overwrite) {
            throw new \InvalidArgumentException("[{$relative}] already exists.");
        }

        $namespace = implode('\\', [
            Str::studly($vendor),
            Str::studly($name),
        ]);

        $class = Str::studly($name) . 'Provider';

        $provider = $namespace . '\\' . $class;

        $stubs = dirname(__DIR__) . '/Console/Commands/stubs';

        $composer = View::parse(
            file_get_contents($stubs . '/composer.stub'),
            compact(
                'name',
                'vendor',
                'description',
                'namespace',
                'provider',
            )
        );

        File::ensureDirectoryExists($path . '/src');

        File::put($path . '/composer.json', $composer);

        $source = View::parse(
            file_get_contents($stubs . '/provider.stub'),
            compact(
                'namespace',
                'class',
            )
        );

        File::put($path . '/src/' . $class . '.php', "<?php\n\n" . $source);

        return [
            'path' => $relative,
            'files' => [
                $relative . '/composer.json',
                $relative . '/src/' . $class . '.php',
            ],
            'provider' => $provider,
        ];
    }
}
