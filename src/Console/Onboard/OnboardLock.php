<?php

namespace Streams\Sdk\Console\Onboard;

use Streams\Sdk\Console\Make\StreamLock;

/**
 * Records hashes for files streams:onboard writes, under the lock key "onboard".
 * Status is implied: these paths are generated and safe to regenerate.
 */
final class OnboardLock
{
    /**
     * @param  list<string>  $absoluteFiles
     */
    public static function record(string $basePath, array $absoluteFiles): void
    {
        $lock = StreamLock::read($basePath);
        $root = rtrim($basePath, '/').'/';

        foreach ($absoluteFiles as $file) {
            if (! is_string($file) || ! is_file($file)) {
                continue;
            }

            $relative = str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
            if ($relative === '' || str_contains($relative, '..')) {
                continue;
            }

            $lock->put('onboard', $relative, StreamLock::hashContents((string) file_get_contents($file)));
        }

        $lock->write($basePath);
    }
}
