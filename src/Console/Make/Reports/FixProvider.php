<?php

namespace Streams\Sdk\Console\Make\Reports;

/**
 * Decides whether a FailedEdit can be solved and proposes solutions for it.
 *
 * This mirrors Spatie Ignition's HasSolutionsForThrowable shape
 * (canSolve(Throwable) / getSolutions(Throwable)) adapted to FailedEdit so
 * the SDK has no Spatie dependency. Providers registered in FixRegistry run
 * after a build, before the report is rendered.
 */
interface FixProvider
{
    public function canSolve(FailedEdit $edit): bool;

    /**
     * @return list<FixSolution>
     */
    public function getSolutions(FailedEdit $edit): array;
}
