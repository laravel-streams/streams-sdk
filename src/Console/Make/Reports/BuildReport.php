<?php

namespace Streams\Sdk\Console\Make\Reports;

use Streams\Sdk\Console\Make\BuildPlan;

/**
 * The end-of-run build report for streams:make.
 *
 * Lists every applied edit (created + modified) and every edit that was not
 * applied (unapplied + conflicts), each with path, file, reason, and the
 * suggested change a fix provider proposes. The same payload backs the text
 * report and the --json report.
 */
class BuildReport
{
    public function __construct(
        protected BuildPlan $plan,
        protected FixRegistry $fixes,
    ) {}

    /**
     * @return list<array{path: string, file: string, reason: string}>
     */
    public function applied(): array
    {
        $applied = [];

        foreach ($this->plan->created as $file) {
            $applied[] = ['path' => $this->nodePath($file), 'file' => $file, 'reason' => 'created'];
        }

        foreach ($this->plan->modified as $file) {
            $applied[] = ['path' => $this->nodePath($file), 'file' => $file, 'reason' => 'modified'];
        }

        return $applied;
    }

    /**
     * @return list<FailedEdit>
     */
    public function failed(): array
    {
        $failed = [];

        foreach (array_merge($this->plan->unapplied, $this->plan->conflicts) as $row) {
            $edit = FailedEdit::fromArray($row);
            $solutions = $this->fixes->solutionsFor($edit);
            $edit->suggestedChange = $solutions === [] ? null : $solutions[0]->getSolutionChangeSummary();
            $failed[] = $edit;
        }

        return $failed;
    }

    /**
     * @return array{applied: list<array{path: string, file: string, reason: string}>, failed: list<array{path: string, file: string, reason: string, suggestedChange: ?string}>}
     */
    public function toArray(): array
    {
        return [
            'applied' => $this->applied(),
            'failed' => array_map(fn (FailedEdit $edit): array => $edit->toArray(), $this->failed()),
        ];
    }

    public function renderText(): string
    {
        $lines = [];

        foreach ($this->applied() as $row) {
            $lines[] = "applied: {$row['path']} {$row['file']} ({$row['reason']})";
        }

        foreach ($this->failed() as $edit) {
            $line = "failed: {$edit->path} {$edit->file} ({$edit->reason})";

            if ($edit->suggestedChange !== null) {
                $line .= " — fix: {$edit->suggestedChange}";
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    protected function nodePath(string $relative): string
    {
        return $this->plan->files[$relative] ?? '';
    }
}
