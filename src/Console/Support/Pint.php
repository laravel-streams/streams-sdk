<?php

namespace Streams\Sdk\Console\Support;

use Symfony\Component\Process\Process;

/**
 * Runs Laravel Pint over generated PHP files as a post-write step.
 *
 * The binary is resolved in order from:
 *  1. config('streams.sdk.pint_path')
 *  2. the host app's vendor/bin/pint
 *  3. the SDK's bundled resources/bin/pint.phar
 *
 * Pint is idempotent — running it twice over the same file produces the
 * same bytes — so a file written through the SDK stays stable across
 * repeated generations.
 */
class Pint
{
    /**
     * Format a PHP file in place with Pint.
     */
    public static function format(string $path): bool
    {
        $binary = static::binary();

        if ($binary === null || ! is_file($path)) {
            return false;
        }

        $process = new Process([PHP_BINARY, $binary, $path]);

        $process->setTimeout(60);

        $process->run();

        return $process->isSuccessful();
    }

    /**
     * The resolved pint binary, or null when none is available.
     */
    public static function binary(): ?string
    {
        try {
            $configured = (string) config('streams.sdk.pint_path', '');
        } catch (\Throwable) {
            $configured = '';
        }

        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        try {
            $hostBinary = function_exists('base_path') ? base_path('vendor/bin/pint') : '';
        } catch (\Throwable) {
            $hostBinary = '';
        }

        if ($hostBinary !== '' && is_file($hostBinary)) {
            return $hostBinary;
        }

        $bundled = dirname(__DIR__, 3).'/resources/bin/pint.phar';

        return is_file($bundled) ? $bundled : null;
    }
}
