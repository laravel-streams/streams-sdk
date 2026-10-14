<?php

namespace Streams\Sdk\Console\Make\Reports;

/**
 * A fix proposed for one FailedEdit.
 *
 * The shape mirrors Spatie Ignition's Solution + RunnableSolution contract:
 * a solution has a title, a description, documentation links, and (when
 * runnable) a run() with parameters. The SDK itself does not require Spatie;
 * see FixSolutionContract for the union that satisfies Spatie interfaces
 * only when they exist.
 */
interface FixSolution
{
    public function getSolutionTitle(): string;

    public function getSolutionDescription(): string;

    /**
     * @return array<string, string>
     */
    public function getDocumentationLinks(): array;

    /**
     * Apply this fix. When the solution is runnable the command calls run();
     * otherwise apply() must do the work.
     */
    public function apply(): bool;

    /**
     * A short line describing the change this fix would make, shown by the
     * report renderer and by --dry-run previews.
     */
    public function getSolutionChangeSummary(): string;
}
