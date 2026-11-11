<?php

namespace Streams\Sdk\Console\Onboard;

/**
 * Discovery answers from streams:onboard. Not a template — a recipe of options
 * generators will honor once kinds/recipes exist.
 *
 * @phpstan-type RecipeArray array{
 *     version: int,
 *     surface: 'laravel'|'js',
 *     frontend: bool,
 *     panels: int,
 *     multi_tenancy: bool,
 *     api: bool,
 *     discover_streams_api: bool,
 *     streams_api_base_url: string|null,
 *     sample_domain: string|null,
 *     created_at: string,
 * }
 */
final class OnboardRecipe
{
    public const VERSION = 1;

    public const DISK_PATH = 'streams.onboard.json';

    public function __construct(
        public readonly string $surface,
        public readonly bool $frontend,
        public readonly int $panels,
        public readonly bool $multiTenancy,
        public readonly bool $api,
        public readonly bool $discoverStreamsApi,
        public readonly ?string $streamsApiBaseUrl,
        public readonly ?string $sampleDomain,
        public readonly string $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $panels = max(0, (int) ($input['panels'] ?? 0));

        return new self(
            surface: (string) ($input['surface'] ?? 'laravel'),
            frontend: (bool) ($input['frontend'] ?? false),
            panels: $panels,
            multiTenancy: (bool) ($input['multi_tenancy'] ?? false),
            api: (bool) ($input['api'] ?? false),
            discoverStreamsApi: (bool) ($input['discover_streams_api'] ?? false),
            streamsApiBaseUrl: isset($input['streams_api_base_url']) && is_string($input['streams_api_base_url']) && $input['streams_api_base_url'] !== ''
                ? $input['streams_api_base_url']
                : null,
            sampleDomain: isset($input['sample_domain']) && is_string($input['sample_domain']) && $input['sample_domain'] !== ''
                ? $input['sample_domain']
                : null,
            createdAt: (string) ($input['created_at'] ?? gmdate('c')),
        );
    }

    /**
     * @return RecipeArray
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'surface' => $this->surface,
            'frontend' => $this->frontend,
            'panels' => $this->panels,
            'multi_tenancy' => $this->multiTenancy,
            'api' => $this->api,
            'discover_streams_api' => $this->discoverStreamsApi,
            'streams_api_base_url' => $this->streamsApiBaseUrl,
            'sample_domain' => $this->sampleDomain,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * Planned generator steps — options only; nothing is emitted yet.
     *
     * @return list<array{id: string, status: string, note: string}>
     */
    public function plannedSteps(): array
    {
        $steps = [
            [
                'id' => 'validate-core',
                'status' => 'ready',
                'note' => 'streams/core present; run streams:validate after stream nodes exist',
            ],
        ];

        if ($this->panels > 0) {
            $steps[] = [
                'id' => 'require-streams-ui',
                'status' => 'pending-generator',
                'note' => "Composer require streams/ui; scaffold {$this->panels} panel(s)",
            ];
        }

        if ($this->api || $this->discoverStreamsApi) {
            $steps[] = [
                'id' => 'require-streams-api',
                'status' => 'pending-generator',
                'note' => $this->discoverStreamsApi
                    ? 'Discover existing Streams API'.($this->streamsApiBaseUrl ? " at {$this->streamsApiBaseUrl}" : '').'; wire client'
                    : 'Composer require streams/api; emit API edge',
            ];
        }

        if ($this->frontend) {
            $steps[] = [
                'id' => 'alpine-entry',
                'status' => 'pending-generator',
                'note' => 'Alpine JS entry + registration (no other client framework)',
            ];
        }

        if ($this->multiTenancy) {
            $steps[] = [
                'id' => 'tenancy',
                'status' => 'pending-generator',
                'note' => 'Tenant resolvers per surface (UI::getTenant / API::getTenant)',
            ];
        }

        if ($this->sampleDomain !== null) {
            $steps[] = [
                'id' => 'domain-'.$this->sampleDomain,
                'status' => 'pending-generator',
                'note' => "Domain recipe: {$this->sampleDomain} (dir, model, repositories, streams.json)",
            ];
        }

        $steps[] = [
            'id' => 'surface',
            'status' => 'info',
            'note' => $this->surface === 'laravel'
                ? 'Path 1: Laravel create-project (this run)'
                : 'Path 2: JS/front-end create flow (coordinate API discovery with a Laravel SALLT app)',
        ];

        return $steps;
    }

    public function write(string $absolutePath): void
    {
        $json = json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

        if (file_put_contents($absolutePath, $json) === false) {
            throw new \RuntimeException("Unable to write onboard recipe [{$absolutePath}].");
        }
    }

    public static function path(?string $base = null): string
    {
        return rtrim($base ?? base_path(), '/').'/'.self::DISK_PATH;
    }
}
