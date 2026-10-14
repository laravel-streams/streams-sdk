<?php

namespace Streams\Sdk\Console\Support;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * @deprecated Use PhpEditor directly. This wrapper exists for backward
 *             compatibility with callers of the #66 whole-file reprint.
 */
class PhpFile
{
    protected PhpEditor $editor;

    public function __construct(protected string $path)
    {
    }

    public static function read(string $path): static
    {
        $file = new static($path);

        $file->editor = PhpEditor::fromString((string) file_get_contents($path), $path);

        return $file;
    }

    /**
     * Build a PhpFile from code rather than the filesystem.
     */
    public static function fromString(string $code, string $path): static
    {
        $file = new static($path);

        $file->editor = PhpEditor::fromString($code, $path);

        return $file;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Add (or update) a keyed item in the file's top-level returned array.
     *
     * @param array<string, bool> $flags
     */
    public function put(string $key, array $flags = []): bool
    {
        return $this->editor->put($key, $flags);
    }

    /**
     * The format-preserving contents of the file.
     */
    public function contents(): string
    {
        return $this->editor->print();
    }

    /**
     * The unified diff between the original contents and the edited
     * contents — exactly what saving the file would apply.
     */
    public function diff(string $original): string
    {
        $differ = new Differ(new UnifiedDiffOutputBuilder("--- {$this->path}\n+++ {$this->path}\n"));

        return $differ->diff($original, $this->contents());
    }
}
