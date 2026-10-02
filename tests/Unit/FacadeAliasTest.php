<?php

declare(strict_types=1);

use Illuminate\Foundation\AliasLoader;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\Gadget;

/**
 * Registers a single config-driven facade alias and reports the aliases the
 * loader ended up with.
 *
 * @return array<string, string>
 */
function registerAlias(mixed $configured): array
{
    AliasLoader::getInstance()->setAliases([]);

    if ($configured !== '__absent__') {
        config()->set('alias_fixture.alias', $configured);
    }

    $provider = new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package
                ->name('alias_fixture')
                ->hasFacadeAlias(Gadget::class, 'alias_fixture.alias');
        }
    };

    $provider->register();

    return AliasLoader::getInstance()->getAliases();
}

afterEach(function (): void {
    AliasLoader::getInstance()->setAliases([]);
});

it('falls back to the class base name when the config key is absent', function (): void {
    expect(registerAlias('__absent__'))->toBe(['Gadget' => Gadget::class]);
});

it('falls back to the class base name when the config value is true', function (): void {
    expect(registerAlias(true))->toBe(['Gadget' => Gadget::class]);
});

it('uses a configured string as the alias name', function (): void {
    expect(registerAlias('Doohickey'))->toBe(['Doohickey' => Gadget::class]);
});

it('skips the alias when the config value is false', function (): void {
    expect(registerAlias(false))->toBe([]);
});

it('skips the alias when the config value is null', function (): void {
    expect(registerAlias(null))->toBe([]);
});

it('skips the alias when the config value is an empty string', function (): void {
    expect(registerAlias(''))->toBe([]);
});

it('falls back to the class base name for a nonsense config value', function (): void {
    expect(registerAlias(42))->toBe(['Gadget' => Gadget::class]);
});

it('skips the alias for an env-style string that parses as false', function (mixed $configured): void {
    expect(registerAlias($configured))->toBe([]);
})->with(['off', '0', 'no', 'false', 'OFF', ' no ', '   ', 0]);

it('falls back to the class base name for an env-style string that parses as true', function (mixed $configured): void {
    expect(registerAlias($configured))->toBe(['Gadget' => Gadget::class]);
})->with(['1', 'on', 'yes', 'true', 'ON', 1]);

it('registers an undeclared alias without any config key', function (): void {
    AliasLoader::getInstance()->setAliases([]);

    $provider = new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('alias_fixture')->hasFacadeAlias(Gadget::class);
        }
    };

    $provider->register();

    expect(AliasLoader::getInstance()->getAliases())->toBe(['Gadget' => Gadget::class]);
});
