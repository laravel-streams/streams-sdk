<?php

namespace Streams\Sdk\Attributes;

use Attribute;

/**
 * Marks code streams:make generated for one app-tree dot path.
 *
 * The argument is the dot path, for example people.actions.add.
 * streams.lock stores the hash of that file. A matching hash means the
 * build still owns the file. A different hash is a hand edit.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::TARGET_PROPERTY)]
class Generated
{
    /**
     * Matches the attribute as streams:make writes it: #[Generated('dot.path')].
     */
    public const SOURCE_PATTERN = '/#\[Generated\(\'([^\']+)\'\)\]/';

    public function __construct(public string $path) {}

    /**
     * Dot path from a #[Generated('...')] attribute in PHP source, if present.
     */
    public static function pathFromSource(string $source): ?string
    {
        if (preg_match(self::SOURCE_PATTERN, $source, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
