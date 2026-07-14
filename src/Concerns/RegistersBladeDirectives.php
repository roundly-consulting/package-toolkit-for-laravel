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
        if ($this->bladeDirectiveTaken($name)) {
            return;
        }

        Blade::directive($name, Closure::fromCallable($handler));
    }

    /**
     * Register a conditional directive — `@name(...) … @elsename … @endname`
     * (plus `@unlessname`) — from a callback returning a boolean.
     *
     * Guarded like {@see self::registerBladeDirective()}: `Blade::if()` expands
     * into four custom directives, so an already-taken name (in any of its
     * forms) is left untouched.
     */
    protected function registerBladeIf(string $name, callable $condition): void
    {
        if ($this->bladeDirectiveTaken($name)) {
            return;
        }

        Blade::if($name, Closure::fromCallable($condition));
    }

    /**
     * Whether the directive name is already registered in any of the forms a
     * plain directive or a `Blade::if()` conditional occupies.
     */
    private function bladeDirectiveTaken(string $name): bool
    {
        $directives = Blade::getCustomDirectives();

        foreach ([$name, 'unless'.$name, 'else'.$name, 'end'.$name] as $candidate) {
            if (array_key_exists($candidate, $directives)) {
                return true;
            }
        }

        return false;
    }
}
