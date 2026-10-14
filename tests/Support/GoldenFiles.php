<?php

namespace Streams\Sdk\Tests\Support;

use FilesystemIterator;
use PHPUnit\Framework\Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/** Runs a generator in a disposable directory and compares every file byte-for-byte. */
final class GoldenFiles
{
    /** The callable receives the output directory; it may invoke an Artisan command there. */
    public static function assertGenerated(callable $generate, string $goldenDirectory): void
    {
        Assert::assertDirectoryExists($goldenDirectory);
        $goldenDirectory = rtrim($goldenDirectory, DIRECTORY_SEPARATOR);
        $directory = sys_get_temp_dir().'/streams-sdk-golden-'.bin2hex(random_bytes(16));
        Assert::assertTrue(mkdir($directory, 0700), 'Cannot create golden-test output directory.');

        try {
            $generate($directory);
            $expected = self::files($goldenDirectory);
            $actual = self::files($directory);
            $paths = array_unique(array_merge(array_keys($expected), array_keys($actual)));
            sort($paths);
            $differences = '';

            foreach ($paths as $path) {
                if (!array_key_exists($path, $expected)) {
                    $differences .= "Unexpected output file: {$path}\n";
                } elseif (!array_key_exists($path, $actual)) {
                    $differences .= "Missing output file: {$path}\n";
                } elseif ($expected[$path] === $actual[$path]) {
                    continue;
                }

                $differ = new Differ(new UnifiedDiffOutputBuilder("--- golden/{$path}\n+++ actual/{$path}\n"));
                $differences .= $differ->diff($expected[$path] ?? '', $actual[$path] ?? '');
            }

            Assert::assertSame('', $differences, "Generated output differs from golden files:\n".$differences);
        } finally {
            self::remove($directory);
        }
    }

    /** @return array<string, string> */
    private static function files(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            Assert::assertFalse($file->isLink(), 'Golden tests do not support symbolic links: '.$file->getPathname());
            Assert::assertTrue($file->isFile(), 'Expected a regular output file: '.$file->getPathname());
            $path = substr($file->getPathname(), strlen($directory) + 1);
            $contents = file_get_contents($file->getPathname());
            Assert::assertNotFalse($contents, 'Cannot read output file: '.$path);
            $files[$path] = $contents;
        }

        ksort($files);

        return $files;
    }

    private static function remove(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
