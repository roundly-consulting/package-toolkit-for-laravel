<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
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

    return gatedRouteIsRegistered();
}

function gatedRouteIsRegistered(): bool
{
    return collect(app('router')->getRoutes()->getRoutes())
        ->contains(static fn ($route): bool => $route->uri() === 'toolbox/disabled');
}

it('skips the route for an env-style string that parses as false', function (mixed $configured): void {
    expect(bootsGatedRoute($configured))->toBeFalse();
})->with(['off', '0', 'no', 'false', 'OFF', 'No', ' no ', "false\n", 0, false]);

it('loads the route for an env-style string that parses as true', function (mixed $configured): void {
    expect(bootsGatedRoute($configured))->toBeTrue();
})->with(['1', 'on', 'yes', 'true', 'ON', 'Yes', ' on ', "true\n", 1, true]);

it('loads the route when the switch is absent, null or blank (not set means the default)', function (mixed $configured): void {
    expect(bootsGatedRoute($configured))->toBeTrue();
})->with(['__absent__', null, '', '   ', "\t", "\n"]);

it('refuses to boot on an unparseable route switch instead of loading the routes', function (mixed $configured, string $given): void {
    try {
        bootsGatedRoute($configured);
    } catch (InvalidConfigurationException $e) {
        expect($e->getMessage())->toBe(
            "Configuration value [route_fixture.enabled] must be a boolean (true/false, 1/0, on/off or yes/no), [{$given}] given.",
        )->and(gatedRouteIsRegistered())->toBeFalse();

        return;
    }

    $this->fail('An unparseable route switch booted without throwing.');
})->with([
    'a word' => ['disabled', 'disabled'],
    'a typo' => ['ture', 'ture'],
    'a maybe' => ['maybe', 'maybe'],
    'a number string' => ['2', '2'],
    'an integer' => [42, '42'],
    'a float' => [1.5, '1.5'],
    'an array' => [['x'], 'array'],
]);
