<?php

namespace Streams\Sdk\Console\Make\Reports\Fixes;

use Streams\Sdk\Console\Make\BuildStage;
use Streams\Sdk\Console\Make\Reports\FailedEdit;
use Streams\Sdk\Console\Make\Reports\FixSolutionContract;

/**
 * Regenerate a conflicted managed file from the compiled app tree.
 *
 * The shape matches Spatie's Solution + RunnableSolution, so when
 * spatie/ignition is installed this fix also surfaces with a one-click
 * "run" button in Ignition and Flare. The SDK itself has no Spatie
 * dependency.
 */
class RegenerateConflictFixSolution implements FixSolutionContract
{
    public function __construct(
        protected BuildStage $stage,
        protected FailedEdit $edit,
        protected string $outputRoot,
    ) {}

    public function getSolutionTitle(): string
    {
        return 'Regenerate from the app tree';
    }

    public function getSolutionDescription(): string
    {
        return sprintf(
            'Overwrite %s with the contents generated from the app tree and refresh its streams.lock hash.',
            $this->edit->file,
        );
    }

    /**
     * @return array<string, string>
     */
    public function getDocumentationLinks(): array
    {
        return [];
    }

    public function getSolutionChangeSummary(): string
    {
        return 'regenerate '.$this->edit->file.' from the app tree';
    }

    public function apply(): bool
    {
        return $this->stage->regenerate($this->edit->path, $this->edit->file, $this->outputRoot);
    }

    /**
     * RunnableSolution: Ignition / Flare can run this fix in one click.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function run(array $parameters = []): void
    {
        $this->apply();
    }

    /**
     * @return array<string, mixed>
     */
    public function getRunParameters(): array
    {
        return [];
    }
}