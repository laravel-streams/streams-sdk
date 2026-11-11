<?php

namespace Streams\Sdk\Console\Onboard;

/**
 * Runs generator steps implied by an OnboardRecipe.
 *
 * Implemented: domain scaffold; manage UI tree + Panel PHP when panels > 0.
 * Composer require streams/ui|api, Alpine, and tenancy remain pending.
 */
final class OnboardApplier
{
    public function __construct(
        protected DomainScaffold $domains = new DomainScaffold,
        protected ManageUiEmitter $manageUi = new ManageUiEmitter,
    ) {}

    /**
     * @return list<array{id: string, status: string, note: string, detail?: array<string, mixed>}>
     */
    public function apply(OnboardRecipe $recipe, string $basePath, bool $force = false): array
    {
        $results = [];
        $domainApplied = null;

        foreach ($recipe->plannedSteps() as $step) {
            $id = $step['id'];

            if (str_starts_with($id, 'domain-') && $step['status'] === 'pending-generator') {
                $domain = substr($id, strlen('domain-'));
                $emit = $this->domains->emit($domain, $basePath, $force);

                $valid = $emit['validation']['valid'] ?? false;
                $domainApplied = $valid ? $domain : null;
                if ($valid) {
                    OnboardLock::record($basePath, $emit['files'] ?? []);
                }

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

            if ($id === 'require-streams-ui' && $step['status'] === 'pending-generator') {
                $domain = $domainApplied ?? $recipe->sampleDomain;
                if ($domain === null || $recipe->panels < 1) {
                    $results[] = [
                        'id' => $id,
                        'status' => 'skipped',
                        'note' => 'No sample domain / panels to scaffold manage UI for',
                    ];

                    continue;
                }

                try {
                    $emit = $this->manageUi->emit($domain, $basePath);
                    OnboardLock::record($basePath, array_merge($emit['tree_files'] ?? [], $emit['php_files'] ?? []));

                    $results[] = [
                        'id' => $id,
                        'status' => 'applied',
                        'note' => 'Manage UI tree + Panel PHP emitted; still Composer-require streams/ui separately',
                        'detail' => $emit,
                    ];
                } catch (\Throwable $e) {
                    $results[] = [
                        'id' => $id,
                        'status' => 'failed',
                        'note' => $e->getMessage(),
                    ];
                }

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

        $streams = [];
        if ($recipe->sampleDomain) {
            $streams[] = \Illuminate\Support\Str::snake($recipe->sampleDomain);
            foreach (['Categories', 'Tags'] as $extra) {
                if ($recipe->sampleDomain === 'Todos') {
                    $emit = $this->domains->emit($extra, $basePath, $force);
                    if ($emit['validation']['valid'] ?? false) {
                        OnboardLock::record($basePath, $emit['files'] ?? []);
                        $streams[] = \Illuminate\Support\Str::snake($extra);
                    }
                }
            }
        }

        $surfaces = (new SurfaceEmitter)->emit(
            $basePath,
            $recipe->frontend,
            $recipe->api || $recipe->discoverStreamsApi,
            $recipe->panels > 0,
            'env-admin',
            $streams,
            $force,
        );
        OnboardLock::record($basePath, $surfaces['files'] ?? []);
        $results[] = [
            'id' => 'surfaces',
            'status' => 'applied',
            'note' => 'API provider, public page, panel base, and env-admin login when those options are on',
            'detail' => $surfaces,
        ];

        return $results;
    }
}
