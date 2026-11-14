<?php

namespace Streams\Sdk\Support;

use Composer\InstalledVersions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\File;
use Streams\Core\Support\Facades\Streams;
use Streams\Core\Stream\Stream;
use Throwable;

/**
 * Where this app actually stands: which Streams packages are installed, what is
 * wired up, and the next command to run. One read model for `streams:tui`, the
 * MCP `streams-status` tool, and `streams:tui --json`, so a beginner and an
 * agent are told the same thing.
 */
class ProjectStatus
{
    public function __construct(protected Application $app)
    {
    }

    /**
     * The package lineup, in the order a beginner should meet it.
     */
    public const PACKAGES = [
        'streams/core' => 'Data platform: streams, fields, criteria, repositories.',
        'streams/ui' => 'Livewire control panels, forms, and tables.',
        'streams/api' => 'A REST API over your streams.',
        'streams/sdk' => 'Generators, checks, the TUI, and this status report (dev only).',
        'streams/testing' => 'Test harness and factories (dev only).',
    ];

    public const SITE = 'https://streams.dev';

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'app' => $this->app(),
            'packages' => $this->packages(),
            'streams' => $this->streams(),
            'artifacts' => $this->artifacts(),
            'mcp' => $this->mcp(),
            'docs' => [
                'index' => static::SITE.'/llms.txt',
                'full_text' => static::SITE.'/llms-full.txt',
                'definition_schema' => static::SITE.'/schema/streams.schema.json',
                'remote_mcp' => static::SITE.'/mcp',
                'start_here' => static::SITE.'/docs/sallt',
            ],
            'next_steps' => $this->nextSteps(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function app(): array
    {
        return [
            'laravel' => $this->app->version(),
            'php' => PHP_VERSION,
            'env' => $this->app->environment(),
            'base_path' => StreamFacts::relative(base_path()) ?: base_path(),
            'mcp_needs' => 'laravel/mcp ^1.0 needs Laravel 11.45+ or 12.41+',
        ];
    }

    /**
     * Which packages are installed, and the version Composer resolved.
     *
     * @return array<string, array<string, mixed>>
     */
    public function packages(): array
    {
        $packages = [];

        foreach (static::PACKAGES as $name => $purpose) {
            $packages[$name] = [
                'installed' => static::isInstalled($name),
                'version' => static::version($name),
                'purpose' => $purpose,
            ];
        }

        $packages['laravel/mcp'] = [
            'installed' => static::isInstalled('laravel/mcp'),
            'version' => static::version('laravel/mcp'),
            'purpose' => 'Runs the MCP server an agent connects to.',
        ];

        return $packages;
    }

    /**
     * @return array<string, mixed>
     */
    public function streams(): array
    {
        $coverage = StreamFacts::coverage();

        return [
            'registered' => $coverage['registered'],
            'definition_files' => $coverage['files'],
            'handles' => Streams::collection()
                ->sortBy(fn (Stream $stream) => (string) $stream->id)
                ->keys()
                ->map(fn ($id) => (string) $id)
                ->all(),
            'unregistered_definitions' => $coverage['unregistered'],
            'registered_without_definition' => $coverage['missing_files'],
        ];
    }

    /**
     * Files the SDK writes, and whether they exist yet. Their absence is a
     * to-do list, not an error.
     *
     * @return array<string, mixed>
     */
    public function artifacts(): array
    {
        return [
            'definitions_dir' => is_dir(StreamFacts::definitionsDirectory()) ? (string) config('streams.sdk.definitions.path', 'streams').'/' : null,
            'onboard_recipe' => is_file(base_path('streams.onboard.json')) ? 'streams.onboard.json' : null,
            'app_tree' => is_file($this->treePath()) ? StreamFacts::relative($this->treePath()) : null,
            'lock' => is_file(base_path('streams.lock')) ? 'streams.lock' : null,
            'entry_schemas' => count(File::glob(base_path('schemas/*.schema.json'))) ?: count(File::glob(base_path('*.schema.json'))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mcp(): array
    {
        $handle = (string) config('streams.sdk.mcp.handle', 'streams');

        return [
            'library' => static::isInstalled('laravel/mcp'),
            'enabled' => (bool) config('streams.sdk.mcp.enabled', true),
            'registered' => static::mcpRegistered($handle),
            'handle' => $handle,
            'read_only' => (bool) config('streams.sdk.mcp.read_only', false),
            'start' => 'php artisan mcp:start '.$handle,
        ];
    }

    public static function mcpRegistered(string $handle): bool
    {
        if (! class_exists(\Laravel\Mcp\Facades\Mcp::class)) {
            return false;
        }

        try {
            return \Laravel\Mcp\Facades\Mcp::getLocalServer($handle) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The short list of what to do next, in order, each with the command that
     * does it. Everything here is safe to read; write steps say so.
     *
     * @return array<int, array<string, string>>
     */
    public function nextSteps(): array
    {
        $steps = [];
        $streams = $this->streams();

        if (! $this->packages()['streams/core']['installed']) {
            $steps[] = $this->step(
                'install-core',
                'Streams needs its data platform before anything else works.',
                'composer require streams/core:2.0.x-dev',
                '/docs/installation',
            );
        }

        if (! $this->packages()['streams/sdk']['installed']) {
            $steps[] = $this->step(
                'install-sdk',
                'The SDK adds the generators, this status report, the TUI, and the agent server.',
                'composer require --dev streams/sdk:1.0.x-dev',
                '/docs/sdk/introduction',
            );
        }

        if ($streams['registered'] === 0) {
            $steps[] = $this->step(
                'first-stream',
                'Nothing to query yet. Describe your data as a stream: fields, rules, source.',
                'php artisan streams:onboard --defaults --apply',
                '/docs/sallt',
            );
        }

        foreach ($streams['unregistered_definitions'] as $id) {
            $steps[] = $this->step(
                'definition-not-registered',
                'streams/'.$id.'.json exists but no stream named '.$id.' is registered, so queries cannot see it. Check its id and that streams/ is loaded at boot.',
                'php artisan streams:validate --json streams/'.$id.'.json',
                '/docs/sdk/stream-schema',
            );
        }

        $steps[] = $this->step(
            'validate',
            'Check every definition against the installed Core before you build on it.',
            'php artisan streams:validate --json',
            '/docs/sdk/commands',
        );

        if (! $this->artifacts()['entry_schemas']) {
            $steps[] = $this->step(
                'generate-types',
                'Typed output is how agents and editors stop guessing. Write one JSON Schema per stream.',
                'php artisan streams:schema',
                '/docs/sdk/stream-schema',
            );
        }

        if (! $this->mcp()['registered']) {
            $steps[] = $this->step(
                'connect-agents',
                'Give your agent the domain model instead of making it read source files.',
                'composer require --dev laravel/mcp && php artisan mcp:start streams',
                '/docs/mcp',
            );
        }

        if ($this->packages()['streams/ui']['installed'] && $streams['registered'] > 0 && ! $this->artifacts()['app_tree']) {
            $steps[] = $this->step(
                'build-ui',
                'Turn a stream into a panel, table, and form from the app JSON tree.',
                'php artisan streams:make --menu',
                '/docs/ui/quick-start',
            );
        }

        return $steps;
    }

    /**
     * @return array<string, string>
     */
    protected function step(string $id, string $why, string $command, string $docs): array
    {
        return [
            'id' => $id,
            'why' => $why,
            'command' => $command,
            'docs' => static::SITE.$docs,
        ];
    }

    protected function treePath(): string
    {
        $tree = (string) config('streams.sdk.make.tree', base_path('streams.json'));

        return str_starts_with($tree, '/') ? $tree : base_path($tree);
    }

    public static function isInstalled(string $package): bool
    {
        if (! class_exists(InstalledVersions::class)) {
            return false;
        }

        try {
            return InstalledVersions::isInstalled($package);
        } catch (Throwable) {
            return false;
        }
    }

    public static function version(string $package): ?string
    {
        if (! static::isInstalled($package)) {
            return null;
        }

        try {
            return InstalledVersions::getPrettyVersion($package);
        } catch (Throwable) {
            return null;
        }
    }
}
