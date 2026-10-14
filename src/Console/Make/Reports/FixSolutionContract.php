<?php

namespace Streams\Sdk\Console\Make\Reports;

use Spatie\Ignition\Contracts\RunnableSolution as SpatieRunnableSolution;
use Spatie\Ignition\Contracts\Solution as SpatieSolution;

/**
 * A FixSolution that also satisfies Spatie Ignition's contracts whenever
 * spatie/ignition is installed.
 *
 * Spatie is optional (composer suggest, never require): without it this
 * interface is just FixSolution. When Spatie's Solution and RunnableSolution
 * interfaces are present, the same interface extends them, so the SDK's fix
 * solutions surface in Ignition and Flare without the engine depending on a
 * spatie package.
 */
if (interface_exists(SpatieSolution::class) && interface_exists(SpatieRunnableSolution::class)) {
    interface FixSolutionContract extends FixSolution, SpatieSolution, SpatieRunnableSolution
    {
    }
} else {
    interface FixSolutionContract extends FixSolution
    {
    }
}