<?php

namespace Streams\Sdk\Console\Make;

/**
 * One generated file held in memory before anything is written.
 */
class CompiledFile
{
    public function __construct(
        public string $nodePath,
        public string $relative,
        public string $contents,
        public string $class,
    ) {}
}
