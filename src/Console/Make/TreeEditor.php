<?php

namespace Streams\Sdk\Console\Make;

use Streams\Sdk\Console\Make\Kinds\KindRegistry;
use Streams\Sdk\Support\AppTree;

/**
 * Writes one validated definition into the app JSON tree at a dot path.
 *
 * The editor follows $ref files and merges the definition onto the node.
 * It does not create or edit PHP files.
 */
class TreeEditor
{
    /**
     * @param  array<string, array<string, array{type: string, map: bool, map_of?: string}>>  $containers
     */
    public function __construct(protected array $containers) {}

    public static function forRegistry(KindRegistry $registry): self
    {
        return new self(AppTree::containersIncluding($registry->treeContainers()));
    }

    /**
     * The component node type at a dot path (action, page, table, stream, ...).
     */
    public function typeAt(string $rootFile, string $path): string
    {
        return $this->walk($rootFile, $path)['type'];
    }

    /** Plan a single map-key rename, retaining any $ref and its contents. */
    public function rename(string $rootFile, string $from, string $to): array
    {
        $parent = substr($from, 0, (int) strrpos($from, '.'));
        $targetParent = substr($to, 0, (int) strrpos($to, '.'));

        if (! $this->segmentsValid($from) || ! $this->segmentsValid($to) || $parent === '' || $parent !== $targetParent || $from === $to) {
            throw new TreeEditException('Rename requires two distinct sibling dot paths.', 'path');
        }

        $state = $this->walk($rootFile, $parent);
        $map = $this->dig($state['data'], $state['keyPath']);
        $old = substr($from, strlen($parent) + 1);
        $new = substr($to, strlen($parent) + 1);

        if ($state['entry'] === null || ! is_array($map) || ! array_key_exists($old, $map) || array_key_exists($new, $map)) {
            throw new TreeEditException('Rename source must exist in a map and destination must be absent.', 'path');
        }

        $renamed = [];
        foreach ($map as $key => $value) {
            $renamed[$key === $old ? $new : $key] = $value;
        }

        $data = $state['data'];
        $cursor = &$data;
        foreach ($state['keyPath'] as $key) {
            $cursor = &$cursor[$key];
        }
        $cursor = $renamed;
        unset($cursor);

        return ['file' => $state['file'], 'contents' => $this->encode($data)];
    }

    /**
     * Merge the definition onto the node and write that JSON file.
     *
     * @param  array<string, mixed>  $input
     * @return array{path: string, file: string, written: bool}
     */
    public function apply(string $rootFile, string $path, array $input, bool $dryRun): array
    {
        $state = $this->walk($rootFile, $path);
        $data = $state['keyPath'] === []
            ? $this->overlay($state['data'], $input)
            : $this->assign($state['data'], $state['keyPath'], $input);

        $encoded = $this->encode($data);

        if (! $dryRun) {
            $directory = dirname($state['file']);

            if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
                throw new TreeEditException("Could not create [{$directory}].");
            }

            if (file_put_contents($state['file'], $encoded) === false) {
                throw new TreeEditException("Could not write [{$state['file']}].");
            }
        }

        return [
            'path' => $path,
            'file' => $this->relative($state['rootDir'], $state['file']),
            'written' => ! $dryRun,
        ];
    }

    /**
     * @return array{rootDir: string, file: string, data: array<string, mixed>, keyPath: list<string>, type: string, entry: ?string}
     */
    protected function walk(string $rootFile, string $path): array
    {
        if (! $this->segmentsValid($path)) {
            throw new TreeEditException("Invalid dot path [{$path}].", 'path');
        }

        if (! is_file($rootFile)) {
            throw new TreeEditException("App tree file not found [{$rootFile}].");
        }

        $rootDir = rtrim(dirname($rootFile), '/');
        $state = [
            'rootDir' => $rootDir,
            'file' => $rootFile,
            'data' => $this->read($rootFile),
            'keyPath' => [],
            'type' => 'root',
            'entry' => null,
        ];

        foreach (explode('.', $path) as $segment) {
            if (preg_match(AppTree::SEGMENT_PATTERN, $segment) !== 1) {
                throw new TreeEditException("Invalid path segment [{$segment}].", 'path');
            }

            if ($state['type'] === 'root') {
                $state = $this->descend($state, ['streams', $segment], 'stream', null);

                continue;
            }

            $spec = $this->specFor($state['type'], $segment);

            if ($spec !== null) {
                $state = $this->descend($state, $spec['via'], $spec['type'], $spec['entry']);

                continue;
            }

            if ($state['entry'] !== null) {
                $state = $this->descend($state, [$segment], $state['entry'], null);

                continue;
            }

            throw new TreeEditException("No component kind fits at [{$path}].", 'path');
        }

        if ($state['type'] === 'root') {
            throw new TreeEditException("No component kind fits at [{$path}].", 'path');
        }

        return $state;
    }

    protected function segmentsValid(string $path): bool
    {
        foreach (explode('.', $path) as $segment) {
            if (preg_match(AppTree::SEGMENT_PATTERN, $segment) !== 1) {
                return false;
            }
        }

        return $path !== '';
    }

    /**
     * @param  array{rootDir: string, file: string, data: array<string, mixed>, keyPath: list<string>, type: string, entry: ?string}  $state
     * @param  list<string>  $keys
     * @return array{rootDir: string, file: string, data: array<string, mixed>, keyPath: list<string>, type: string, entry: ?string}
     */
    protected function descend(array $state, array $keys, string $type, ?string $entry): array
    {
        foreach ($keys as $key) {
            $state['keyPath'][] = $key;
            $node = $this->dig($state['data'], $state['keyPath']);
            $ref = $this->reference($node);

            if ($ref === null) {
                continue;
            }

            $state['file'] = $this->absolute($state['rootDir'], $ref);
            $state['data'] = is_file($state['file']) ? $this->read($state['file']) : [];
            $state['keyPath'] = [];
        }

        $state['type'] = $type;
        $state['entry'] = $entry;

        return $state;
    }

    /**
     * @return array{via: list<string>, type: string, entry: ?string}|null
     */
    protected function specFor(string $type, string $segment): ?array
    {
        if ($type === 'stream' && isset($this->containers['ui'][$segment])) {
            $spec = $this->containers['ui'][$segment];

            return $this->describeSpec(['ui', $segment], $spec, $segment);
        }

        $table = $this->containers[$type] ?? [];

        if (! isset($table[$segment]) || ! is_array($table[$segment])) {
            return null;
        }

        return $this->describeSpec([$segment], $table[$segment], $segment);
    }

    /**
     * @param  list<string>  $via
     * @param  array{type: string, map: bool, map_of?: string}  $spec
     * @return array{via: list<string>, type: string, entry: ?string}
     */
    protected function describeSpec(array $via, array $spec, string $segment): array
    {
        if ($spec['map']) {
            return [
                'via' => $via,
                'type' => $segment,
                'entry' => (string) ($spec['map_of'] ?? $spec['type']),
            ];
        }

        return [
            'via' => $via,
            'type' => (string) $spec['type'],
            'entry' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keyPath
     */
    protected function dig(array $data, array $keyPath): mixed
    {
        $cursor = $data;

        foreach ($keyPath as $key) {
            if (! is_array($cursor) || ! array_key_exists($key, $cursor)) {
                return null;
            }

            $cursor = $cursor[$key];
        }

        return $cursor;
    }

    protected function reference(mixed $node): ?string
    {
        if (! is_array($node) || ! isset($node['$ref']) || ! is_string($node['$ref'])) {
            return null;
        }

        return array_keys($node) === ['$ref'] ? $node['$ref'] : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function overlay(array $data, array $input): array
    {
        foreach ($input as $key => $value) {
            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keyPath
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function assign(array $data, array $keyPath, array $input): array
    {
        $key = array_shift($keyPath);

        if (! is_string($key)) {
            return $data;
        }

        if ($keyPath === []) {
            $existing = (isset($data[$key]) && is_array($data[$key]) && $this->reference($data[$key]) === null)
                ? $data[$key]
                : [];

            $data[$key] = $this->overlay($existing, $input);

            return $data;
        }

        $child = (isset($data[$key]) && is_array($data[$key]) && $this->reference($data[$key]) === null)
            ? $data[$key]
            : [];

        $data[$key] = $this->assign($child, $keyPath, $input);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function encode(array $data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new TreeEditException('Could not encode the app tree as JSON.');
        }

        return $json."\n";
    }

    /**
     * @return array<string, mixed>
     */
    protected function read(string $file): array
    {
        $data = json_decode((string) file_get_contents($file), true);

        if (! is_array($data)) {
            throw new TreeEditException("App tree file [{$file}] does not contain a JSON object.");
        }

        return $data;
    }

    protected function absolute(string $rootDir, string $ref): string
    {
        if (str_starts_with($ref, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $ref) === 1) {
            return $ref;
        }

        return $rootDir.'/'.$ref;
    }

    protected function relative(string $rootDir, string $file): string
    {
        $prefix = $rootDir.'/';

        return str_starts_with($file, $prefix) ? substr($file, strlen($prefix)) : $file;
    }
}
