<?php

namespace Streams\Sdk\Console\Make;

/**
 * Resolves generated file paths and namespaces for streams:make.
 *
 * Templates come from config('streams.sdk.make.paths') and fall back to the
 * GroupVitals reference layout: app/Domains/{Domain}/Panel/Tables/{Class}.php
 * (and the matching Pages, Actions, Resources, Forms, and Public directories).
 *
 * Tokens: {root}, {Domain}, {Class}.
 */
class Paths
{
    /**
     * Reference defaults. Directory shape matches the pinned People references.
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'root' => 'app/Domains',
        'namespace' => 'App\\Domains',
        'table' => '{root}/{Domain}/Panel/Tables/{Class}.php',
        'page' => '{root}/{Domain}/Panel/Pages/{Class}.php',
        'action' => '{root}/{Domain}/Panel/Actions/{Class}.php',
        'panel' => '{root}/{Domain}/Panel/Resources/{Class}.php',
        'form' => '{root}/{Domain}/Panel/Forms/{Class}.php',
        'public_page' => '{root}/{Domain}/Public/Pages/{Class}.php',
    ];

    /**
     * Node types that generate a PHP file.
     *
     * @var list<string>
     */
    public const FILE_TYPES = [
        'table',
        'page',
        'action',
        'panel',
        'form',
        'public_page',
    ];

    /**
     * @param  array<string, string>  $templates
     */
    public function __construct(protected array $templates) {}

    public static function fromConfig(): static
    {
        $configured = config('streams.sdk.make.paths', []);

        if (! is_array($configured)) {
            $configured = [];
        }

        return new static(array_merge(self::DEFAULTS, $configured));
    }

    /**
     * Relative path for a code-bearing node, or null when the type has no file.
     */
    public function resolve(string $type, string $domain, string $class): ?string
    {
        $template = $this->templates[$type] ?? null;

        if (! is_string($template) || $template === '') {
            return null;
        }

        return strtr($template, [
            '{root}' => trim((string) $this->templates['root'], '/'),
            '{Domain}' => $domain,
            '{Class}' => $class,
        ]);
    }

    /**
     * PSR-4 namespace for a relative file path under the configured root.
     */
    public function namespaceFor(string $relativeFile): string
    {
        $root = trim((string) $this->templates['root'], '/');
        $base = trim((string) $this->templates['namespace'], '\\');
        $directory = trim(str_replace('\\', '/', dirname($relativeFile)), '/');

        $prefix = $root.'/';

        if ($directory === $root) {
            return $base;
        }

        if (str_starts_with($directory, $prefix)) {
            $directory = substr($directory, strlen($prefix));
        }

        if ($directory === '' || $directory === '.') {
            return $base;
        }

        return $base.'\\'.str_replace('/', '\\', $directory);
    }

    /**
     * Directory scanned for generated files that no longer have a tree node.
     */
    public function managedRoot(): string
    {
        return trim((string) $this->templates['root'], '/');
    }
}
