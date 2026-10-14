<?php

namespace Streams\Sdk\Console\Make;

use Streams\Sdk\Console\Make\Kinds\KindRegistry;
use Streams\Sdk\Support\AppTree;

/**
 * The page menu: pages in the app tree and each page's components by path.
 *
 * The menu reads the tree only. Save and override write settings onto a
 * component's node through TreeEditor; they never write PHP.
 */
class PageMenu
{
    public function __construct(protected KindRegistry $registry) {}

    public static function forRegistry(KindRegistry $registry): self
    {
        return new self($registry);
    }

    /**
     * Page nodes in the tree, addressed by dot path (people.pages.manage).
     *
     * @return list<string>
     */
    public function pages(AppTree $tree): array
    {
        $pages = [];

        foreach ($tree->types() as $path => $type) {
            if ($path !== '' && $type === 'page') {
                $pages[] = $path;
            }
        }

        sort($pages);

        return $pages;
    }

    /**
     * A page's components by dot path: every node under the page's stream
     * that a registered kind fits, including the page itself.
     *
     * @return list<array{path: string, type: string, kind: string}>
     */
    public function components(AppTree $tree, string $page): array
    {
        $stream = strtok($page, '.');
        $stream = is_string($stream) ? $stream : $page;

        if ($stream === '' || $stream === $page) {
            $stream = str_contains($page, '.') ? substr($page, 0, (int) strpos($page, '.')) : $page;
        }

        $components = [];

        foreach ($tree->types() as $path => $type) {
            if ($path === '' || $path === $stream) {
                continue;
            }

            if ($path !== $stream && ! str_starts_with($path, $stream.'.')) {
                continue;
            }

            $kind = $this->registry->forType($type);

            if ($kind === null) {
                continue;
            }

            $components[] = [
                'path' => $path,
                'type' => $type,
                'kind' => $kind->name,
            ];
        }

        usort($components, fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        return $components;
    }
}