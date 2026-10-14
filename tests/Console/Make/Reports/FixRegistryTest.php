<?php

namespace Streams\Sdk\Tests\Console\Make\Reports;

use Streams\Sdk\Console\Make\BuildPlan;
use Streams\Sdk\Console\Make\Reports\BuildReport;
use Streams\Sdk\Console\Make\Reports\FailedEdit;
use Streams\Sdk\Console\Make\Reports\FixProvider;
use Streams\Sdk\Console\Make\Reports\FixRegistry;
use Streams\Sdk\Console\Make\Reports\FixSolution;
use Streams\Sdk\Tests\SdkTestCase;

/**
 * The build report lists applied and failed edits with path, file, reason,
 * and suggested change in text and JSON. Fix providers mirror Spatie's
 * HasSolutionsForThrowable with our own canSolve/getSolutions contract and
 * the SDK has no Spatie dependency.
 */
class FixRegistryTest extends SdkTestCase
{
    public function test_registry_runs_providers_in_order(): void
    {
        $registry = new FixRegistry([new HandEditConflictProvider]);

        $edit = FailedEdit::fromArray([
            'path' => 'people',
            'file' => 'app/Support/People/People.php',
            'reason' => 'hand edit; hash does not match streams.lock',
        ]);

        $this->assertInstanceOf(FixProvider::class, $registry->providerFor($edit));
        $this->assertCount(1, $registry->solutionsFor($edit));
        $this->assertInstanceOf(FixSolution::class, $registry->solutionsFor($edit)[0]);

        $unsolved = FailedEdit::fromArray([
            'path' => 'people',
            'file' => 'app/Support/People/People.php',
            'reason' => 'cannot apply edit [declare class People]: no class declaration found',
        ]);

        $this->assertNull($registry->providerFor($unsolved));
        $this->assertSame([], $registry->solutionsFor($unsolved));
    }

    public function test_report_lists_applied_and_failed_edits_with_suggested_change(): void
    {
        $plan = new BuildPlan;
        $plan->created = ['app/Support/People/PeopleInput.php'];
        $plan->modified = ['app/Support/People/PeopleModel.php'];
        $plan->files['app/Support/People/PeopleInput.php'] = 'people.input';
        $plan->files['app/Support/People/PeopleModel.php'] = 'people.model';
        $plan->conflicts = [[
            'path' => 'people',
            'file' => 'app/Support/People/People.php',
            'reason' => 'hand edit; hash does not match streams.lock',
        ]];

        $registry = new FixRegistry([new HandEditConflictProvider]);
        $report = new BuildReport($plan, $registry);

        $array = $report->toArray();

        $this->assertSame([
            ['path' => 'people.input', 'file' => 'app/Support/People/PeopleInput.php', 'reason' => 'created'],
            ['path' => 'people.model', 'file' => 'app/Support/People/PeopleModel.php', 'reason' => 'modified'],
        ], $array['applied']);

        $this->assertSame('people', $array['failed'][0]['path']);
        $this->assertSame('app/Support/People/People.php', $array['failed'][0]['file']);
        $this->assertStringContainsString('hand edit', $array['failed'][0]['reason']);
        $this->assertSame('regenerate app/Support/People/People.php from the app tree', $array['failed'][0]['suggestedChange']);

        $text = $report->renderText();

        $this->assertStringContainsString('applied: people.input app/Support/People/PeopleInput.php (created)', $text);
        $this->assertStringContainsString('failed: people app/Support/People/People.php (hand edit; hash does not match streams.lock)', $text);
        $this->assertStringContainsString('— fix: regenerate app/Support/People/People.php from the app tree', $text);
    }

    public function test_conflict_solution_summary_names_the_file(): void
    {
        $edit = FailedEdit::fromArray([
            'path' => 'people',
            'file' => 'app/Support/People/People.php',
            'reason' => 'hand edit; hash does not match streams.lock',
        ]);

        $solution = (new HandEditConflictProvider)->getSolutions($edit)[0];

        $this->assertSame('Regenerate from the app tree', $solution->getSolutionTitle());
        $this->assertStringContainsString('app/Support/People/People.php', $solution->getSolutionDescription());
        $this->assertSame('regenerate app/Support/People/People.php from the app tree', $solution->getSolutionChangeSummary());
    }
}

class HandEditConflictProvider implements FixProvider
{
    public function canSolve(FailedEdit $edit): bool
    {
        return str_contains($edit->reason, 'hand edit');
    }

    public function getSolutions(FailedEdit $edit): array
    {
        return [new class($edit) implements FixSolution
        {
            public function __construct(protected FailedEdit $edit) {}

            public function apply(): bool
            {
                return true;
            }

            public function getSolutionTitle(): string
            {
                return 'Regenerate from the app tree';
            }

            public function getSolutionDescription(): string
            {
                return 'Overwrite '.$this->edit->file.' with generated contents.';
            }

            public function getDocumentationLinks(): array
            {
                return [];
            }

            public function getSolutionChangeSummary(): string
            {
                return 'regenerate '.$this->edit->file.' from the app tree';
            }
        }];
    }
}
