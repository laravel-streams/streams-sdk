<?php

namespace Streams\Sdk\Console\Make\Reports;

/**
 * Runs the FixProviders that can solve the failed edits of a build.
 *
 * Providers are asked in registration order. The first provider that can
 * solve a FailedEdit provides the solutions for that edit. The engine has
 * no Spatie dependency: this mirrors Spatie's solution registry with our
 * own canSolve(FailedEdit) / getSolutions(FailedEdit) contract.
 */
class FixRegistry
{
    /**
     * @var list<FixProvider>
     */
    protected array $providers = [];

    /**
     * @param  list<FixProvider>  $providers
     */
    public function __construct(array $providers = [])
    {
        foreach ($providers as $provider) {
            $this->add($provider);
        }
    }

    public function add(FixProvider $provider): self
    {
        $this->providers[] = $provider;

        return $this;
    }

    /**
     * @return list<FixProvider>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    /**
     * The first provider that can solve $edit.
     */
    public function providerFor(FailedEdit $edit): ?FixProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider->canSolve($edit)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * @return list<FixSolution>
     */
    public function solutionsFor(FailedEdit $edit): array
    {
        $provider = $this->providerFor($edit);

        if ($provider === null) {
            return [];
        }

        return $provider->getSolutions($edit);
    }
}