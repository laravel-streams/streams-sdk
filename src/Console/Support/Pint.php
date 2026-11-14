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
     * Why the last format() or usable() call failed, for callers that report it.
     */
    public static string $lastError = '';

    /**
     * Commands print the pint warning at most once per run.
     */
    public static bool $warnedOnce = false;

    /**
     * Format a PHP file in place with Pint.
     */
    public static function format(string $path): bool
    {
        $binary = static::binary();

        if ($binary === null || ! is_file($path)) {
            static::$lastError = 'no pint binary resolved';

            return false;
        }

        $process = new Process([PHP_BINARY, $binary, $path]);

        $process->setTimeout(60);

        $process->run();

        if (! $process->isSuccessful()) {
            static::$lastError = trim($process->getErrorOutput()."\n".$process->getOutput())
                ?: 'pint exited with '.$process->getExitCodeText();
        } else {
            static::$lastError = '';
        }

        return $process->isSuccessful();
    }

    /**
     * Whether the resolved pint binary actually runs on this PHP.
     *
     * The bundled pint.phar declares its own platform requirements, and they can
     * be stricter than this package's - the shipped build needs PHP ^8.3 while
     * streams-sdk supports ^8.2, so on 8.2 the post-write formatting step fails
     * silently and generated files simply are not formatted. Callers that assert
     * formatting happened should ask this first and say why they skipped.
     */
    public static function usable(): bool
    {
        $binary = static::binary();

        if ($binary === null) {
            static::$lastError = 'no pint binary resolved';

            return false;
        }

        $process = new Process([PHP_BINARY, $binary, '--version']);

        $process->setTimeout(30);

        $process->run();

        if (! $process->isSuccessful()) {
            static::$lastError = 'pint ('.basename($binary).') will not run on PHP '
                .PHP_VERSION.': '.(trim($process->getErrorOutput()."\n".$process->getOutput())
                    ?: 'exit '.$process->getExitCodeText());

            return false;
        }

        static::$lastError = '';

        return true;
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
