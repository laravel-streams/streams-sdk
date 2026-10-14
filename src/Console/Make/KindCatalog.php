<?php

namespace Streams\Sdk\Console\Make;

use Streams\Sdk\Console\Make\Kinds\ChainLink;
use Streams\Sdk\Console\Make\Kinds\InputField;
use Streams\Sdk\Console\Make\Kinds\Kind;
use Streams\Sdk\Console\Make\Kinds\KindRegistry;
use Streams\Sdk\Support\AppTree;

/**
 * Makeable kinds and the dot paths where each one fits.
 */
class KindCatalog
{
    /**
     * @param  array<string, array<string, array{type: string, map: bool, map_of?: string}>>  $containers
     */
    public function __construct(
        protected KindRegistry $registry,
        protected array $containers,
    ) {}

    public static function forRegistry(KindRegistry $registry): self
    {
        return new self($registry, AppTree::containersIncluding($registry->treeContainers()));
    }

    /**
     * @return list<array{kind: string, source: string, description: string, paths: list<string>, nodes: list<string>, inputs: list<array{field: string, flag: ?string, required: bool, default: mixed, prompt: ?string}>, package?: string}>
     */
    public function entries(?AppTree $tree): array
    {
        $handles = $this->streamHandles($tree);
        $rows = [];

        foreach ($this->registry->all() as $kind) {
            $patterns = $this->patterns($kind);
            $paths = $this->concrete($patterns, $handles);
            $nodes = $this->nodes($tree, $kind);
            $row = [
                'kind' => $kind->name,
                'source' => (string) $kind->source,
                'description' => $kind->description,
                'paths' => $paths,
                'nodes' => $nodes,
                'inputs' => array_map(fn (InputField $field): array => [
                    'field' => $field->key,
                    'flag' => $field->flag,
                    'required' => $field->required,
                    'default' => $field->default,
                    'prompt' => $field->prompt,
                ], $kind->inputs),
            ];

            if ($kind->package !== null) {
                $row['package'] = $kind->package;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function patterns(Kind $kind): array
    {
        if ($kind->chain !== []) {
            return array_map(
                fn (ChainLink $link): string => '{stream}.'.$link->path,
                $kind->chain,
            );
        }

        $found = [];
        $this->walk('stream', '', $kind->resolvedType(), [], $found);

        if ($found === [] && $kind->resolvedType() === 'stream') {
            return ['{stream}'];
        }

        sort($found);

        return $found;
    }

    /**
     * @param  list<string>  $stack
     * @param  list<string>  $found
     */
    protected function walk(string $type, string $suffix, string $target, array $stack, array &$found): void
    {
        if (in_array($type, $stack, true)) {
            return;
        }

        $stack[] = $type;

        if ($type === $target && ($suffix !== '' || $target === 'stream')) {
            $found[] = $suffix === '' ? '{stream}' : '{stream}'.$suffix;
        }

        foreach ($this->specsFor($type) as $key => $spec) {
            if ($spec['map']) {
                $nextType = (string) ($spec['map_of'] ?? $spec['type']);
                $this->walk($nextType, $suffix.'.'.$key.'.{handle}', $target, $stack, $found);

                continue;
            }

            $this->walk((string) $spec['type'], $suffix.'.'.$key, $target, $stack, $found);
        }
    }

    /**
     * @return array<string, array{type: string, map: bool, map_of?: string}>
     */
    protected function specsFor(string $type): array
    {
        if ($type !== 'stream') {
            return $this->containers[$type] ?? [];
        }

        $specs = $this->containers['ui'] ?? [];

        foreach ($this->containers['stream'] ?? [] as $key => $spec) {
            if ($key === 'ui' || isset($specs[$key])) {
                continue;
            }

            $specs[$key] = $spec;
        }

        return $specs;
    }

    /**
     * @param  list<string>  $patterns
     * @param  list<string>  $handles
     * @return list<string>
     */
    protected function concrete(array $patterns, array $handles): array
    {
        if ($handles === []) {
            return $patterns;
        }

        $paths = [];

        foreach ($handles as $handle) {
            foreach ($patterns as $pattern) {
                $paths[] = str_replace('{stream}', $handle, $pattern);
            }
        }

        $paths = array_values(array_unique($paths));
        sort($paths);

        return $paths;
    }

    /**
     * @return list<string>
     */
    protected function streamHandles(?AppTree $tree): array
    {
        if ($tree === null) {
            return [];
        }

        $handles = [];

        foreach ($tree->types() as $path => $type) {
            if ($type === 'stream' && $path !== '') {
                $handles[] = $path;
            }
        }

        sort($handles);

        return $handles;
    }

    /**
     * @return list<string>
     */
    protected function nodes(?AppTree $tree, Kind $kind): array
    {
        if ($tree === null) {
            return [];
        }

        $nodes = [];

        foreach ($tree->types() as $path => $type) {
            if ($path === '' || $type !== $kind->resolvedType()) {
                continue;
            }

            $nodes[] = $path;
        }

        sort($nodes);

        return $nodes;
    }
}
