<?php

namespace Streams\Sdk\Console\Make;

use Composer\InstalledVersions;
use Illuminate\Foundation\Application;

/**
 * Detects the installed Laravel and Streams versions and resolves the variant
 * id that selects the reference/transform set used by streams:make.
 *
 * The variant set is kept small on purpose: every combination that the
 * reference app supports today resolves to the same default variant, and a
 * new variant is only introduced when an installed version pair needs a
 * different reference layout. The map (config('streams.sdk.make.variant_map'))
 * and the per-variant reference templates (config('streams.sdk.make.variants'))
 * are published config so an app can add its own variants without touching
 * generator code.
 */
class Variants
{
    /**
     * Variant used when no version pair is mapped and when nothing is
     * configured. References the GroupVitals reference layout
     * (Paths::DEFAULTS).
     */
    public const DEFAULT_VARIANT = 'v1';

    /**
     * Default small variant map: version-pair key -> variant id.
     *
     * Keys are "laravel-{major}.streams-{major}". Unmapped pairs fall back to
     * DEFAULT_VARIANT.
     *
     * @var array<string, string>
     */
    public const MAP = [
        'laravel-11.streams-2' => self::DEFAULT_VARIANT,
        'laravel-12.streams-2' => self::DEFAULT_VARIANT,
    ];

    /**
     * Resolve the variant id for the installed Laravel and Streams versions.
     *
     * Versions can be injected for tests; by default they are read from the
     * running application and the installed streams/core package.
     */
    public static function detect(?string $laravel = null, ?string $streams = null): string
    {
        $map = config('streams.sdk.make.variant_map', self::MAP);

        if (! is_array($map)) {
            $map = self::MAP;
        }

        $key = 'laravel-'.self::major($laravel ?? self::laravelVersion())
            .'.streams-'.self::major($streams ?? self::streamsVersion());

        if (isset($map[$key]) && is_string($map[$key])) {
            return $map[$key];
        }

        return self::DEFAULT_VARIANT;
    }

    /**
     * Path/namespace templates that apply for a variant id.
     *
     * These are merged over Paths::DEFAULTS, so an empty set keeps the
     * reference layout. Values come from the published config so variants can
     * be added without editing generator code.
     *
     * @return array<string, string>
     */
    public static function referencesFor(string $variant): array
    {
        $variants = config('streams.sdk.make.variants', []);

        if (! is_array($variants) || ! isset($variants[$variant]) || ! is_array($variants[$variant])) {
            return [];
        }

        return $variants[$variant];
    }

    /**
     * Major version, as an integer string ("12" from "12.0.0").
     */
    protected static function major(string $version): string
    {
        $major = explode('.', $version)[0] ?? '';

        return ctype_digit($major) ? $major : '0';
    }

    protected static function laravelVersion(): string
    {
        return Application::VERSION;
    }

    protected static function streamsVersion(): string
    {
        try {
            $version = InstalledVersions::getPrettyVersion('streams/core');
        } catch (\OutOfBoundsException $e) {
            $version = null;
        }

        if (! is_string($version) || $version === '') {
            $version = (string) config('streams.sdk.make.streams_version', '2');
        }

        return $version;
    }
}