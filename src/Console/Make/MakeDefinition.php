<?php

namespace Streams\Sdk\Console\Make;

use Streams\Sdk\Console\Make\Kinds\Kind;

/**
 * One validated component definition.
 *
 * Prompts, flags, and a JSON recipe all fill this same object. The tree
 * editor writes `input` at `path` and does not build PHP.
 */
class MakeDefinition
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public readonly string $path,
        public readonly Kind $kind,
        public readonly array $input,
    ) {}

    /**
     * @return array{path: string, kind: string, input: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'kind' => $this->kind->name,
            'input' => $this->input,
        ];
    }
}
