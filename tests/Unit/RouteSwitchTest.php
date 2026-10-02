<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

/**
 * Boots a provider whose single route file is gated behind `route_fixture.enabled`,
 * and reports whether the route it declares was registered.
 */
function bootsGatedRoute(mixed $configured): bool
{
    if ($configured !== '__absent__') {
        config()->set('route_fixture.enabled', $configured);
    }

    $provider = new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            // The Toolbox fixture's `disabled.php` — its own switch keeps it unloaded,
            // so whatever this provider does is the only thing that can register it.
            $package->name('route_fixture')->hasRoutes('disabled.php', 'route_fixture.enabled');
        }

        protected function resolvePackageBasePath(): string
        {
            return __DIR__.'/../Fixtures/Toolbox/toolbox';
        }
    };

    $provider->register();
    $provider->boot();

    return collect(app('router')->getRoutes()->getRoutes())
        ->contains(static fn ($route): bool => $route->uri() === 'toolbox/disabled');
}

it('skips the route for an env-style string that parses as false', function (mixed $configured): void {
    expect(bootsGatedRoute($configured))->toBeFalse();
})->with(['off', '0', 'no', 'false', 'OFF', ' no ', '', 0, false]);

it('loads the route for an env-style string that parses as true', function (mixed $configured): void {
    expect(bootsGatedRoute($configured))->toBeTrue();
})->with(['1', 'on', 'yes', 'true', 'ON', 1, true]);

it('falls back to loading the route when the switch is missing or unparseable', function (mixed $configured): void {
    expect(bootsGatedRoute($configured))->toBeTrue();
})->with(['__absent__', null, 'maybe', 'ture', 42]);
