<?php

namespace Streams\Sdk\Console\Make;

/**
 * The plan for one streams:make run.
 *
 * created, modified, skipped, and unapplied are computed before any write,
 * so a dry run and the real run that follows report the same plan.
 * Nothing in the plan deletes a file.
 */
class BuildPlan
{
    /**
     * Relative paths that do not exist yet.
     *
     * @var list<string>
     */
    public array $created = [];

    /**
     * Relative paths whose managed contents differ and can be edited.
     *
     * @var list<string>
     */
    public array $modified = [];

    /**
     * Generated files left in place because their node is no longer in the tree.
     *
     * @var list<string>
     */
    public array $skipped = [];

    /**
     * Edits that were not written. Each item has path, file, and reason.
     *
     * @var list<array{path: string, file: string, reason: string}>
     */
    public array $unapplied = [];

    /**
     * Unified diffs keyed by relative path, for created and modified files.
     *
     * @var array<string, string>
     */
    public array $diffs = [];

    /**
     * @return array{created: list<string>, modified: list<string>, skipped: list<string>, unapplied: list<array{path: string, file: string, reason: string}>, diff: string}
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'modified' => $this->modified,
            'skipped' => $this->skipped,
            'unapplied' => $this->unapplied,
            'diff' => $this->unifiedDiff(),
        ];
    }

    public function unifiedDiff(): string
    {
        if ($this->diffs === []) {
            return '';
        }

        ksort($this->diffs);

        return rtrim(implode('', $this->diffs), "\n")."\n";
    }
}
