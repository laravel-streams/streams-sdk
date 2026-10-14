<?php

namespace Streams\Sdk\Console\Make\Kinds;

/**
 * Where a kind sits in the app tree.
 *
 * A map placement (`ui.badges` of `badge`) lets a package add a dot path
 * such as `people.badges.star` without a new command.
 */
class Placement
{
    public function __construct(
        public readonly string $parent,
        public readonly string $key,
        public readonly bool $map,
        public readonly ?string $mapOf = null,
    ) {}
}
