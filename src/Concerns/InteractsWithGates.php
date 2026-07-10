<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Concerns;

use Illuminate\Support\Facades\Gate;

/**
 * Idempotent authorization-gate registration for a package's service provider.
 * A gate that a host application has already defined is left untouched, so a
 * package never overrides the host's own policy for the same ability.
 */
trait InteractsWithGates
{
    protected function defineGate(string $ability, callable|string $callback): void
    {
        if (Gate::has($ability)) {
            return;
        }

        Gate::define($ability, $callback);
    }
}
