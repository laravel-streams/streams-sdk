<?php

namespace Streams\Sdk\Console\Make;

/**
 * streams.lock records the hash of each file streams:make last wrote.
 *
 * Shape: dot path -> relative file -> sha256 hex of the file bytes.
 * Keys are sorted so two runs with the same tree write the same bytes.
 */
class StreamLock
{
    public const FILENAME = 'streams.lock';

    public const HASH_ALGO = 'sha256';

    /**
     * @param  array<string, array<string, string>>  $entries
     */
    public function __construct(public array $entries = []) {}

    public static function path(string $outputRoot): string
    {
        return rtrim($outputRoot, '/').'/'.self::FILENAME;
    }

    public static function read(string $outputRoot): self
    {
        $path = self::path($outputRoot);

        if (! is_file($path)) {
            return new self;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return new self;
        }

        $entries = [];

        foreach ($decoded as $nodePath => $files) {
            if (! is_string($nodePath) || ! is_array($files)) {
                continue;
            }

            foreach ($files as $file => $hash) {
                if (is_string($file) && is_string($hash)) {
                    $entries[$nodePath][$file] = $hash;
                }
            }
        }

        return new self($entries);
    }

    public function hash(string $nodePath, string $file): ?string
    {
        return $this->entries[$nodePath][$file] ?? null;
    }

    /** Lock mappings may only select relative PHP files inside the output root. */
    public static function validFile(string $file): bool
    {
        return $file !== ''
            && ! str_starts_with($file, '/')
            && ! str_contains($file, '\\')
            && preg_match('/^[A-Za-z]:/', $file) !== 1
            && ! in_array('..', explode('/', $file), true)
            && str_ends_with($file, '.php');
    }

    public function put(string $nodePath, string $file, string $hash): void
    {
        $this->entries[$nodePath][$file] = $hash;
    }

    public static function hashContents(string $contents): string
    {
        return hash(self::HASH_ALGO, $contents);
    }

    /**
     * Pretty JSON with sorted keys and a trailing newline.
     */
    public function encode(): string
    {
        $entries = $this->entries;
        ksort($entries);

        foreach ($entries as $nodePath => $files) {
            ksort($files);
            $entries[$nodePath] = $files;
        }

        if ($entries === []) {
            return "{}\n";
        }

        return json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    public function write(string $outputRoot): void
    {
        $path = self::path($outputRoot);
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $this->encode());
    }
}
