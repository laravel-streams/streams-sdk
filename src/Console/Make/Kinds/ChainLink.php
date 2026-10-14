<?php

namespace Streams\Sdk\Console\Make\Kinds;

/**
 * One step in a chained kind.
 *
 * A string value that starts with `$` copies that key from the parent input.
 * Every other value is a literal written onto the child node.
 */
class ChainLink
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $path,
        public readonly array $input,
    ) {}

    /**
     * @param  array<string, mixed>  $parent
     * @return array<string, mixed>
     */
    public function resolve(array $parent): array
    {
        $resolved = [];

        foreach ($this->input as $key => $value) {
            if (is_string($value) && str_starts_with($value, '$')) {
                $source = substr($value, 1);

                if (! array_key_exists($source, $parent) || $parent[$source] === null) {
                    continue;
                }

                $resolved[$key] = $parent[$source];

                continue;
            }

            $resolved[$key] = $value;
        }

        return $resolved;
    }
}
