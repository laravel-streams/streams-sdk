<?php

namespace Streams\Sdk\Console\Make;

/**
 * The in-memory file tree streams:make plans against.
 *
 * Generation fills this tree first. The build stage diffs it with the
 * working tree and only then adds or edits files on disk.
 */
class MemoryTree
{
    /**
     * @var list<CompiledFile>
     */
    protected array $files = [];

    public function add(CompiledFile $file): void
    {
        $this->files[] = $file;
    }

    /**
     * @return list<CompiledFile>
     */
    public function files(): array
    {
        return $this->files;
    }
}
