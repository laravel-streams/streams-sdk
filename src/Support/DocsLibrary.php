<?php

namespace Streams\Sdk\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * The docs an app already has on disk: the app's own docs/, the docs shipped
 * inside installed Streams packages, and any docs-style streams. Local files
 * only — no network — because the agent is working offline against the version
 * it is running, and streams.dev is the source of truth for anything newer.
 */
class DocsLibrary
{
    /**
     * Cached index of every page: id, title, description, tags, path, body.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function index(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(static::cacheKey());
        }

        return Cache::remember(
            static::cacheKey(),
            now()->addMinutes(10),
            fn () => static::build(),
        );
    }

    public static function cacheKey(): string
    {
        return 'streams.sdk.docs.index.'.md5(implode('|', static::patterns()));
    }

    /**
     * Glob patterns from config, relative to the app root.
     *
     * @return array<int, string>
     */
    public static function patterns(): array
    {
        $patterns = (array) config('streams.sdk.mcp.docs.paths', [
            'docs',
            'vendor/streams/*/docs',
            'streams/data/*docs',
        ]);

        return array_values(array_filter(array_map('strval', $patterns)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function build(): array
    {
        $pages = [];
        $seen = [];

        foreach (static::roots() as $directory) {
            $finder = Finder::create()
                ->files()
                ->name('*.md')
                ->in($directory)
                ->ignoreUnreadableDirs()
                ->ignoreDotFiles(true)
                ->ignoreVCS(true);

            foreach ($finder as $file) {
                $real = $file->getRealPath();

                if ($real === false || isset($seen[$real])) {
                    continue;
                }

                $seen[$real] = true;

                $page = static::parse($real);

                if ($page !== null) {
                    $pages[] = $page;
                }
            }
        }

        usort($pages, fn (array $a, array $b) => strcmp($a['id'], $b['id']));

        return $pages;
    }

    /**
     * Config patterns expanded to existing directories. A pattern may itself
     * contain a glob (`vendor/streams/{pkg}/docs`), which `Finder` will not
     * expand.
     *
     * @return array<int, string>
     */
    public static function roots(): array
    {
        $roots = [];

        foreach (static::patterns() as $pattern) {
            $absolute = str_starts_with($pattern, '/') ? $pattern : base_path($pattern);

            foreach (str_contains($absolute, '*') ? (glob($absolute, GLOB_ONLYDIR) ?: []) : [$absolute] as $directory) {
                if (is_dir($directory)) {
                    $roots[] = (string) realpath($directory);
                }
            }
        }

        return array_values(array_unique($roots));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function parse(string $absolute): ?array
    {
        $contents = (string) file_get_contents($absolute);

        [$frontmatter, $body] = static::split($contents);

        $id = StreamFacts::relative($absolute);

        return [
            'id' => $id,
            'title' => (string) ($frontmatter['title'] ?? $frontmatter['nav_title'] ?? Str::title(basename($id, '.md'))),
            'nav_title' => (string) ($frontmatter['nav_title'] ?? ''),
            'description' => (string) ($frontmatter['description'] ?? Str::limit(trim(preg_replace('/\s+/', ' ', static::firstParagraph($body)) ?: ''), 200)),
            'package' => (string) ($frontmatter['package'] ?? static::guessPackage($id)),
            'section' => (string) ($frontmatter['section'] ?? ''),
            'tags' => array_values(array_map('strval', (array) ($frontmatter['tags'] ?? []))),
            'status' => (string) ($frontmatter['status'] ?? ''),
            'path' => $id,
            'body' => trim($body),
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected static function split(string $contents): array
    {
        if (! preg_match('/^---\R(.*?)\R---\R?(.*)$/s', $contents, $matches)) {
            return [[], $contents];
        }

        try {
            $frontmatter = Yaml::parse($matches[1]);
        } catch (\Throwable) {
            $frontmatter = [];
        }

        return [is_array($frontmatter) ? $frontmatter : [], $matches[2]];
    }

    protected static function firstParagraph(string $body): string
    {
        foreach (preg_split('/\R\R+/', trim($body)) ?: [] as $block) {
            $block = trim($block);

            if ($block === '' || str_starts_with($block, '#') || str_starts_with($block, '```') || str_starts_with($block, '|')) {
                continue;
            }

            return strip_tags($block);
        }

        return '';
    }

    /**
     * docs/packages/core/… and vendor/streams/core/docs/… both mean "core".
     */
    protected static function guessPackage(string $path): string
    {
        if (preg_match('#(?:packages|vendor/streams|streams)/(core|ui|api|sdk|testing|client|dev)/#', $path, $matches)) {
            return $matches[1];
        }

        return 'app';
    }

    /**
     * Every term must appear in the title, description, tags, id, or body.
     * Titles weigh most, which is what makes a one-word query useful.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function search(string $query, ?string $package = null, int $limit = 10): array
    {
        $needle = static::normalize($query);
        $terms = array_values(array_unique(array_filter(
            preg_split('/[^a-z0-9_.:\-\/]+/', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn (string $term) => strlen($term) > 1,
        )));

        if ($terms === []) {
            return [];
        }

        $results = [];

        foreach (static::index() as $page) {
            if ($package !== null && $page['package'] !== $package) {
                continue;
            }

            $title = static::normalize($page['title'].' '.$page['nav_title'].' '.basename((string) $page['id']));
            $description = static::normalize($page['description']);
            $tags = static::normalize(implode(' ', $page['tags']));
            $id = static::normalize($page['id']);
            $body = static::normalize($page['body']);

            $score = 0;

            foreach ($terms as $term) {
                $hit = 0;
                $hit += str_contains($title, $term) ? 10 : 0;
                $hit += str_contains($id, $term) ? 6 : 0;
                $hit += str_contains($tags, $term) ? 5 : 0;
                $hit += str_contains($description, $term) ? 4 : 0;
                $hit += min(substr_count($body, $term), 5);

                if ($hit === 0) {
                    continue 2;
                }

                $score += $hit;
            }

            $results[] = [
                'id' => $page['id'],
                'title' => $page['title'],
                'description' => $page['description'],
                'package' => $page['package'],
                'tags' => $page['tags'],
                'status' => $page['status'],
                'snippet' => static::snippet($page['body'], $terms),
                'score' => $score,
            ];
        }

        usort($results, fn (array $a, array $b) => [$b['score'], $a['title']] <=> [$a['score'], $b['title']]);

        return array_slice($results, 0, max(1, $limit));
    }

    /**
     * One page by id ("docs/packages/sdk/commands.md"), by path with the
     * extension dropped, or by basename ("commands.md").
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $id): ?array
    {
        $want = trim(str_replace('\\', '/', $id), '/');

        foreach (static::index() as $page) {
            if ($page['id'] === $want || $page['id'] === preg_replace('/\.md$/', '', $want).'.md' || basename((string) $page['id']) === basename($want)) {
                return $page;
            }
        }

        return null;
    }

    protected static function normalize(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    /**
     * @param  array<int, string>  $terms
     */
    protected static function snippet(string $body, array $terms, int $radius = 90): string
    {
        $text = preg_replace('/```[\s\S]*?```/', ' ', $body) ?? $body;
        $text = strip_tags($text);
        $text = trim(preg_replace('/\s+/', ' ', preg_replace('/[#*`>\[\]|]/', '', $text) ?? '') ?? '');

        $source = static::normalize($text);
        $needle = static::normalize($text);
        $position = null;

        foreach ($terms as $term) {
            $found = strpos($needle, $term);

            if ($found !== false && ($position === null || $found < $position)) {
                $position = $found;
            }
        }

        if ($position === null) {
            return Str::limit($source, $radius * 2);
        }

        $start = max(0, $position - $radius);

        return ($start > 0 ? '…' : '').trim(substr($source, $start, $radius * 2));
    }
}
