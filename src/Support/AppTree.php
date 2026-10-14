<?php

namespace Streams\Sdk\Support;

use Illuminate\Support\Str;

/**
 * Loads an app tree (root streams.json plus $ref node files) into one tree
 * and resolves any node by its dot path.
 *
 * Every node is addressed by a dot path that starts at the stream handle:
 * people, people.table, people.actions, people.actions.add,
 * people.actions.add.form.sections.basics, people.pages.manage. There are no
 * generated ids; the path segments are the snake_case/slug names the author
 * wrote.
 *
 * A node is either a file ($ref) or an inline object. $ref paths are resolved
 * relative to the app root (the directory of streams.json). The same file
 * cannot be loaded twice, and no two siblings in one map may derive the same
 * StudlyCase class name.
 */
class AppTree
{
    /**
     * Valid path segments: snake_case or slug, lowercase, starting with a letter.
     */
    public const SEGMENT_PATTERN = '/^[a-z][a-z0-9_-]*$/';

    /**
     * Named child containers per node type.
     *
     * `map: true` keys are container nodes whose entries are the child nodes
     * of `map_of`. `map: false` keys are a single child node of `type`.
     *
     * @var array<string, array<string, array{type: string, map: bool, map_of?: string}>>
     */
    protected array $containers = [
        'root' => [
            'streams' => ['type' => 'streams', 'map' => true, 'map_of' => 'stream'],
        ],
        'stream' => [
            'ui' => ['type' => 'ui', 'map' => false],
        ],
        'ui' => [
            'panel' => ['type' => 'panel', 'map' => false],
            'table' => ['type' => 'table', 'map' => false],
            'pages' => ['type' => 'pages', 'map' => true, 'map_of' => 'page'],
            'public_pages' => ['type' => 'public_pages', 'map' => true, 'map_of' => 'public_page'],
            'actions' => ['type' => 'actions', 'map' => true, 'map_of' => 'action'],
            'forms' => ['type' => 'forms', 'map' => true, 'map_of' => 'form'],
        ],
        'table' => [
            'columns' => ['type' => 'columns', 'map' => true, 'map_of' => 'column'],
            'actions' => ['type' => 'actions', 'map' => true, 'map_of' => 'action'],
        ],
        'action' => [
            'actions' => ['type' => 'actions', 'map' => true, 'map_of' => 'action'],
            'form' => ['type' => 'form', 'map' => false],
        ],
        'form' => [
            'sections' => ['type' => 'sections', 'map' => true, 'map_of' => 'section'],
        ],
    ];

    /**
     * @var array<string, array> Nodes keyed by dot path.
     */
    protected array $nodes = [];

    /**
     * @var array<string, string> The node type for each dot path.
     */
    protected array $types = [];

    /**
     * @var array<string, string> The file each dot path was loaded from, when any.
     */
    protected array $files = [];

    /**
     * @var array<string, string> realpath => the dot path that first loaded it.
     */
    protected array $loadedFiles = [];

    /**
     * The app root directory; $ref paths resolve against this.
     */
    protected string $root;

    public static function load(string $rootFile): static
    {
        $tree = new static();

        $tree->root = rtrim(dirname($rootFile), '/');

        $tree->loadFile($rootFile, '', 'root');

        return $tree;
    }

    /**
     * Resolve a dot path to its node.
     *
     * @throws AppTreeException When the path is unknown.
     */
    public function node(string $path): array
    {
        if (!isset($this->nodes[$path])) {
            throw new AppTreeException("Unknown app tree node [{$path}].");
        }

        return $this->nodes[$path];
    }

    public function has(string $path): bool
    {
        return isset($this->nodes[$path]);
    }

    /**
     * The node type for a dot path (stream, table, action, ...).
     */
    public function type(string $path): ?string
    {
        return $this->types[$path] ?? null;
    }

    /**
     * The file a node was loaded from, relative to the app root, when any.
     */
    public function file(string $path): ?string
    {
        return $this->files[$path] ?? null;
    }

    /**
     * All registered nodes keyed by dot path.
     *
     * @return array<string, array>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * All registered node types keyed by dot path.
     *
     * @return array<string, string>
     */
    public function types(): array
    {
        return $this->types;
    }

    /**
     * The app root streams.json payload.
     */
    public function root(): array
    {
        return $this->nodes[''];
    }

    /**
     * The StudlyCase class name derived from a full dot path.
     */
    public function classForPath(string $path): string
    {
        return Str::studly(str_replace('.', '_', $path));
    }

    /**
     * The StudlyCase class name derived from one path segment.
     */
    public function classForSegment(string $segment): string
    {
        return Str::studly($segment);
    }

    /**
     * Assert that a segment follows the snake_case/slug rules.
     *
     * @throws AppTreeException
     */
    public function assertSegment(string $segment): void
    {
        if (!preg_match(self::SEGMENT_PATTERN, $segment)) {
            throw new AppTreeException(
                "Invalid path segment [{$segment}]. Segments must be snake_case or slug: " .
                'lowercase letters, numbers, underscores or hyphens, starting with a letter.'
            );
        }
    }

    protected function loadFile(string $file, string $path, string $type): void
    {
        $real = realpath($file);

        if ($real === false) {
            throw new AppTreeException("App tree file not found [{$file}] (node [{$path}]).");
        }

        if (isset($this->loadedFiles[$real])) {
            throw new AppTreeException(
                "Duplicate node [{$path}]: file [{$file}] is already loaded for [{$this->loadedFiles[$real]}]."
            );
        }

        $this->loadedFiles[$real] = $path;

        $this->files[$path] = $this->relativeToRoot($file);

        $contents = (string) file_get_contents($real);

        $data = json_decode($contents, true);

        if (!is_array($data)) {
            throw new AppTreeException("App tree file [{$file}] does not contain a JSON object.");
        }

        $this->register($path, $type, $data, $file);
    }

    /**
     * Register one node at its dot path and descend into its named children.
     */
    protected function register(string $path, string $type, array $data, ?string $file = null): void
    {
        if ($path !== '') {
            $this->assertSegments($path);
        }

        if (isset($this->nodes[$path])) {
            throw new AppTreeException("Duplicate node name [{$path}]: the path is already registered.");
        }

        $this->nodes[$path] = $data;
        $this->types[$path] = $type;

        if ($type === 'root') {
            // The root's `streams` map is anonymous: its entries are the
            // streams, addressed directly at their handles (people, ...).
            $this->registerMapEntries('', 'streams', 'stream', $data['streams'] ?? []);

            return;
        }

        if ($type === 'stream') {
            $this->registerUiChildren($path, $data['ui'] ?? []);

            return;
        }

        foreach ($this->containers[$type] ?? [] as $key => $spec) {
            if (!array_key_exists($key, $data) || !is_array($data[$key])) {
                continue;
            }

            $childPath = $this->joinPath($path, $key);

            if ($spec['map']) {
                $this->register($childPath, $key, $data[$key]);
                $this->registerMapEntries($childPath, $key, $spec['map_of'], $data[$key]);

                continue;
            }

            $this->registerNodeAt($childPath, $spec['type'], $data[$key]);
        }
    }

    /**
     * A stream's ui subtree: panel/table are single nodes, the plural keys
     * are container nodes under the stream handle.
     */
    protected function registerUiChildren(string $streamPath, array $ui): void
    {
        foreach ($this->containers['ui'] as $key => $spec) {
            if (!array_key_exists($key, $ui) || !is_array($ui[$key])) {
                continue;
            }

            $childPath = $this->joinPath($streamPath, $key);

            if ($spec['map']) {
                $this->register($childPath, $key, $ui[$key]);
                $this->registerMapEntries($childPath, $key, $spec['map_of'], $ui[$key]);

                continue;
            }

            $this->registerNodeAt($childPath, $spec['type'], $ui[$key]);
        }
    }

    /**
     * Register the entries of a container node (actions, pages, columns, ...).
     */
    protected function registerMapEntries(string $containerPath, string $containerType, string $childType, array $map): void
    {
        $seen = [];

        foreach ($map as $segment => $node) {
            if (!is_string($segment) || !is_array($node)) {
                continue;
            }

            $this->assertSegment($segment);

            $class = $this->classForSegment($segment);

            if (isset($seen[$class]) && $seen[$class] !== $segment) {
                throw new AppTreeException(
                    "Duplicate name in [{$containerPath}]: [{$seen[$class]}] and [{$segment}] both derive " .
                    "the StudlyCase class [{$class}]."
                );
            }

            $seen[$class] = $segment;

            $this->registerNodeAt($this->joinPath($containerPath, $segment), $childType, $node);
        }
    }

    /**
     * Register a single child node, loading its file when it is a $ref.
     */
    protected function registerNodeAt(string $path, string $type, array $node): void
    {
        if (is_array($node) && isset($node['$ref']) && is_string($node['$ref'])) {
            $this->loadFile($this->root . '/' . $node['$ref'], $path, $type);

            return;
        }

        $this->register($path, $type, $node);
    }

    protected function joinPath(string $parent, string $segment): string
    {
        return $parent === '' ? $segment : $parent . '.' . $segment;
    }

    /**
     * Express a loaded file path relative to the app root.
     */
    protected function relativeToRoot(string $file): string
    {
        $root = realpath($this->root);

        $real = realpath($file);

        if ($root !== false && $real !== false && str_starts_with($real, $root . '/')) {
            return substr($real, strlen($root) + 1);
        }

        return $file;
    }

    protected function assertSegments(string $path): void
    {
        foreach (explode('.', $path) as $segment) {
            $this->assertSegment($segment);
        }
    }
}
