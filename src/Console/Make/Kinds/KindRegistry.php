<?php

namespace Streams\Sdk\Console\Make\Kinds;

use Streams\Sdk\Support\AppTree;

/**
 * Registry of component kinds streams:make can produce.
 *
 * The SDK, an app, and a package each register kinds through this API.
 * Registration stores data only. It does not register an Artisan command.
 */
class KindRegistry
{
    /**
     * @var array<string, Kind>
     */
    protected array $kinds = [];

    /**
     * Node type => kind name.
     *
     * @var array<string, string>
     */
    protected array $byType = [];

    public static function withBuiltIns(): self
    {
        $registry = new self;

        foreach (BuiltInKinds::all() as $kind) {
            $registry->registerFromSdk($kind);
        }

        return $registry;
    }

    /**
     * Read app and package kinds from config('streams.sdk.make.kinds').
     */
    public function loadConfigured(): self
    {
        $configured = config('streams.sdk.make.kinds', []);

        if (! is_array($configured)) {
            return $this;
        }

        foreach ($configured['app'] ?? [] as $kind) {
            if ($kind instanceof Kind) {
                $this->registerFromApp($kind);
            }
        }

        $packages = $configured['packages'] ?? [];

        if (is_array($packages)) {
            foreach ($packages as $package => $kinds) {
                if (! is_string($package) || ! is_array($kinds)) {
                    continue;
                }

                foreach ($kinds as $kind) {
                    if ($kind instanceof Kind) {
                        $this->registerFromPackage($kind, $package);
                    }
                }
            }
        }

        return $this;
    }

    public function registerFromSdk(Kind $kind): void
    {
        $this->add($kind->fromSource('sdk'));
    }

    public function registerFromApp(Kind $kind): void
    {
        $this->add($kind->fromSource('app'));
    }

    public function registerFromPackage(Kind $kind, string $package): void
    {
        $this->add($kind->fromSource('package', $package));
    }

    public function has(string $name): bool
    {
        return isset($this->kinds[$name]);
    }

    public function get(string $name): Kind
    {
        if (! isset($this->kinds[$name])) {
            throw new KindRegistryException("Unknown component kind [{$name}].");
        }

        return $this->kinds[$name];
    }

    /**
     * The kind for an app-tree node type, when one is registered.
     */
    public function forType(string $type): ?Kind
    {
        $name = $this->byType[$type] ?? null;

        return $name === null ? null : $this->kinds[$name];
    }

    /**
     * The kind streams:make uses for a dot path, when the node has one.
     */
    public function forPath(AppTree $tree, string $path): ?Kind
    {
        $type = $tree->type($path);

        if ($type === null) {
            return null;
        }

        return $this->forType($type);
    }

    /**
     * @return list<Kind>
     */
    public function all(): array
    {
        return array_values($this->kinds);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->kinds);
    }

    /**
     * @return list<Kind>
     */
    public function from(string $source): array
    {
        return array_values(array_filter(
            $this->kinds,
            fn (Kind $kind): bool => $kind->source === $source,
        ));
    }

    /**
     * Extra app-tree containers contributed by registered kinds.
     *
     * Built-in containers stay on AppTree. A package kind adds its own
     * parent key here so a dot path can reach it.
     *
     * @return array<string, array<string, array{type: string, map: bool, map_of?: string}>>
     */
    public function treeContainers(): array
    {
        $containers = [];

        foreach ($this->kinds as $kind) {
            $placement = $kind->placement;

            if ($placement === null) {
                continue;
            }

            if (isset($containers[$placement->parent][$placement->key])) {
                throw new KindRegistryException("Container [{$placement->parent}.{$placement->key}] is already claimed by a component kind.");
            }

            $spec = [
                'type' => $placement->map ? $placement->key : $kind->resolvedType(),
                'map' => $placement->map,
            ];

            if ($placement->map) {
                $spec['map_of'] = $placement->mapOf ?? $kind->resolvedType();
            }

            $containers[$placement->parent][$placement->key] = $spec;
        }

        return $containers;
    }

    protected function add(Kind $kind): void
    {
        if (isset($this->kinds[$kind->name])) {
            throw new KindRegistryException("Component kind [{$kind->name}] is already registered.");
        }

        $type = $kind->resolvedType();

        if (isset($this->byType[$type])) {
            throw new KindRegistryException("Node type [{$type}] is already bound to component kind [{$this->byType[$type]}].");
        }

        $this->kinds[$kind->name] = $kind;
        $this->byType[$type] = $kind->name;
    }
}
