<?php

namespace Streams\Sdk\Console\Make\Reports;

use Streams\Sdk\Console\Make\BuildPlan;

/**
 * The end-of-run build report for streams:make.
 *
 * Lists every applied edit (created + modified), every edit a fix provider
 * applied (fixed), and every edit that was not applied (unapplied +
 * conflicts), each with path, file, reason, and the suggested change a fix
 * provider proposes. The same payload backs the text report and the --json
 * report.
 */
class BuildReport
{
    /**
     * @var list<FailedEdit>
     */
    protected array $fixed = [];

    public function __construct(
        protected BuildPlan $plan,
        protected FixRegistry $fixes,
    ) {}

    /**
     * Replace the plan after fixes have rewritten files. Fixed records are
     * kept so --fix does not lose the record of edits it applied.
     */
    public function setPlan(BuildPlan $plan): void
    {
        $this->plan = $plan;
    }

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
        return array_merge($this->unapplied(), $this->conflicts());
    }

    /**
     * @return list<FailedEdit>
     */
    public function unapplied(): array
    {
        return $this->failedEdits($this->plan->unapplied);
    }

    /**
     * @return list<FailedEdit>
     */
    public function conflicts(): array
    {
        return $this->failedEdits($this->plan->conflicts);
    }

    public function recordFixed(FailedEdit $edit): void
    {
        $this->fixed[] = $edit;
    }

    /**
     * @return list<FailedEdit>
     */
    public function fixed(): array
    {
        return $this->fixed;
    }

    /**
     * @param  list<array{path: string, file: string, reason: string}>  $rows
     * @return list<FailedEdit>
     */
    protected function failedEdits(array $rows): array
    {
        $failed = [];

        foreach ($rows as $row) {
            $edit = FailedEdit::fromArray($row);
            $solutions = $this->fixes->solutionsFor($edit);
            $edit->suggestedChange = $solutions === [] ? null : $solutions[0]->getSolutionChangeSummary();
            $failed[] = $edit;
        }

        return $failed;
    }

    /**
     * @return array{applied: list<array{path: string, file: string, reason: string}>, fixed: list<array{path: string, file: string, reason: string, suggestedChange: ?string}>, failed: list<array{path: string, file: string, reason: string, suggestedChange: ?string}>}
     */
    public function toArray(): array
    {
        return [
            'applied' => $this->applied(),
            'fixed' => array_map(fn (FailedEdit $edit): array => $edit->toArray(), $this->fixed()),
            'failed' => array_map(fn (FailedEdit $edit): array => $edit->toArray(), $this->failed()),
        ];
    }

    public function renderText(): string
    {
        $lines = [];

        foreach ($this->applied() as $row) {
            $lines[] = "applied: {$row['path']} {$row['file']} ({$row['reason']})";
        }

        foreach ($this->fixed() as $edit) {
            $lines[] = "fixed: {$edit->path} {$edit->file} — {$edit->suggestedChange}";
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
