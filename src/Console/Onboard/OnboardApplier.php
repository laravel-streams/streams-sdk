<?php

namespace Streams\Sdk\Console\Onboard;

/**
 * Runs generator steps implied by an OnboardRecipe.
 *
 * Today: domain scaffold only. UI / API / tenancy / Alpine remain pending.
 */
final class OnboardApplier
{
    public function __construct(
        protected DomainScaffold $domains = new DomainScaffold,
    ) {}

    /**
     * @return list<array{id: string, status: string, note: string, detail?: array<string, mixed>}>
     */
    public function apply(OnboardRecipe $recipe, string $basePath, bool $force = false): array
    {
        $results = [];

        foreach ($recipe->plannedSteps() as $step) {
            $id = $step['id'];

            if (str_starts_with($id, 'domain-') && $step['status'] === 'pending-generator') {
                $domain = substr($id, strlen('domain-'));
                $emit = $this->domains->emit($domain, $basePath, $force);

                $valid = $emit['validation']['valid'] ?? false;
                $results[] = [
                    'id' => $id,
                    'status' => $valid ? 'applied' : 'failed',
                    'note' => $valid
                        ? 'Domain plumbing written; stream source validates'
                        : 'Domain files written but stream source validation failed',
                    'detail' => $emit,
                ];

                continue;
            }

            if ($step['status'] === 'pending-generator') {
                $results[] = [
                    'id' => $id,
                    'status' => 'skipped',
                    'note' => 'Generator not implemented yet: '.$step['note'],
                ];

                continue;
            }

            $results[] = [
                'id' => $id,
                'status' => $step['status'],
                'note' => $step['note'],
            ];
        }

        return $results;
    }
}
