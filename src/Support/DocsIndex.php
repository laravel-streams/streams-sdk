<?php

namespace Streams\Sdk\Support;

use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\File;

/**
 * A small keyword index over local markdown documentation
 * (the app's docs/, installed Streams packages' docs/,
 * and docs streams in streams/data).
 */
class DocsIndex
{
    protected ?array $documents = null;

    /**
     * @param array<int, string> $patterns Glob patterns relative to $basePath.
     */
    public function __construct(
        protected array $patterns,
        protected string $basePath
    ) {
    }

    /**
     * @return array<int, array{id: string, title: string, score: int, snippet: string}>
     */
    public function search(string $query, int $limit = 10): array
    {
        $query = Str::lower(trim($query));

        $terms = array_values(array_unique(array_filter(
            preg_split('/[^\p{L}\p{N}_:.-]+/u', $query) ?: [],
            fn ($term) => mb_strlen($term) > 1
        )));

        if (!$terms) {
            return [];
        }

        $results = [];

        foreach ($this->documents() as $document) {

            $title = Str::lower($document['title']);
            $body = Str::lower($document['body']);
            $id = Str::lower($document['id']);

            $score = 0;
            $matched = 0;

            foreach ($terms as $term) {

                $hits = substr_count($title, $term) * 10
                    + substr_count($id, $term) * 3
                    + min(substr_count($body, $term), 20);

                if ($hits) {
                    $matched++;
                }

                $score += $hits;
            }

            if (!$matched) {
                continue;
            }

            // Favor documents matching every term and the exact phrase.
            $score *= $matched;

            if (count($terms) > 1 && str_contains($body, $query)) {
                $score += 25;
            }

            $results[] = [
                'id' => $document['id'],
                'title' => $document['title'],
                'score' => $score,
                'snippet' => $this->snippet($document['body'], $terms),
            ];
        }

        usort($results, fn ($a, $b) => $b['score'] <=> $a['score'] ?: strcmp($a['id'], $b['id']));

        return array_slice($results, 0, max(1, $limit));
    }

    /**
     * Read a document by the ID returned from search().
     * Only indexed documents can be read.
     */
    public function read(string $id): ?array
    {
        foreach ($this->documents() as $document) {
            if ($document['id'] === $id) {
                return $document;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{id: string, title: string, path: string, body: string}>
     */
    public function documents(): array
    {
        if ($this->documents !== null) {
            return $this->documents;
        }

        $documents = [];
        $seen = [];

        foreach ($this->patterns as $pattern) {

            $matches = glob(rtrim($this->basePath, '/') . '/' . ltrim($pattern, '/')) ?: [];

            foreach ($matches as $match) {

                $files = is_dir($match)
                    ? File::allFiles($match)
                    : [new \SplFileInfo($match)];

                foreach ($files as $file) {

                    if (!in_array(strtolower($file->getExtension()), ['md', 'markdown'])) {
                        continue;
                    }

                    $real = $file->getRealPath() ?: $file->getPathname();

                    if (isset($seen[$real])) {
                        continue;
                    }

                    $seen[$real] = true;

                    $documents[] = $this->document($file->getPathname());
                }
            }
        }

        usort($documents, fn ($a, $b) => strcmp($a['id'], $b['id']));

        return $this->documents = $documents;
    }

    protected function document(string $path): array
    {
        $contents = (string) file_get_contents($path);

        $meta = [];
        $body = $contents;

        if (preg_match('/^---\R(.*?)\R---\R?(.*)$/s', $contents, $matches)) {

            try {
                $meta = (array) Yaml::parse($matches[1]);
            } catch (\Throwable $e) {
                $meta = [];
            }

            $body = $matches[2];
        }

        $title = $meta['title'] ?? null;

        if (!$title && preg_match('/^#\s+(.+)$/m', $body, $heading)) {
            $title = trim($heading[1]);
        }

        if (!$title) {
            $title = Str::headline(preg_replace('/^\d+[-_]/', '', pathinfo($path, PATHINFO_FILENAME)));
        }

        return [
            'id' => ltrim(Str::after($path, rtrim($this->basePath, '/')), '/'),
            'title' => (string) $title,
            'path' => $path,
            'body' => $body,
        ];
    }

    protected function snippet(string $body, array $terms, int $length = 280): string
    {
        $lower = Str::lower($body);
        $position = null;

        foreach ($terms as $term) {
            $found = mb_strpos($lower, $term);

            if ($found !== false && ($position === null || $found < $position)) {
                $position = $found;
            }
        }

        $start = max(0, ($position ?? 0) - 80);

        $snippet = trim(preg_replace('/\s+/', ' ', mb_substr($body, $start, $length)));

        return ($start > 0 ? '…' : '') . $snippet . (mb_strlen($body) > $start + $length ? '…' : '');
    }
}
