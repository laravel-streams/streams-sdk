<?php

namespace Streams\Sdk\Console\Make\Reports\Fixes;

use Streams\Sdk\Console\Make\BuildStage;
use Streams\Sdk\Console\Make\Reports\FailedEdit;
use Streams\Sdk\Console\Make\Reports\FixProvider;

/**
 * Sample fix provider that solves one known failure: a hand-edited file
 * whose hash no longer matches streams.lock.
 *
 * The solution regenerates the managed file from the compiled app tree and
 * refreshes its lock hash. Every other failure is left for the developer.
 */
class RegenerateConflictFixProvider implements FixProvider
{
    public function __construct(
        protected BuildStage $stage,
        protected string $outputRoot,
    ) {}

    public function canSolve(FailedEdit $edit): bool
    {
        return str_contains($edit->reason, BuildStage::CONFLICT_REASON);
    }

    /**
     * @return list<RegenerateConflictFixSolution>
     */
    public function getSolutions(FailedEdit $edit): array
    {
        return [new RegenerateConflictFixSolution($this->stage, $edit, $this->outputRoot)];
    }
}
