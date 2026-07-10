<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Concerns;

use Closure;
use Illuminate\Support\Facades\Blade;

/**
 * Idempotent Blade directive registration for a package's service provider.
 * Registering the same directive name twice is a no-op, so a double-boot (e.g.
 * package discovery plus an explicit provider registration) can't clobber a
 * directive or double-register it.
 */
trait RegistersBladeDirectives
{
    protected function registerBladeDirective(string $name, callable $handler): void
    {
        if (array_key_exists($name, Blade::getCustomDirectives())) {
            return;
        }

        Blade::directive($name, Closure::fromCallable($handler));
    }
}
