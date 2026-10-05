<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\AbstractGreeter;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\ChimingDoorbell;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\Doorbell;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\EnglishGreeter;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\FormalSalutation;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\FrenchGreeter;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\Greeter;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\Salutation;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\Widget;

// The Toolbox fixture calls `bindFromConfig(Greeter::class, 'toolbox.greeter', EnglishGreeter::class)`
// in `register()`; the binding reads config when the contract is resolved.

it('binds the default when the key is absent', function (): void {
    $toolbox = config('toolbox');
    unset($toolbox['greeter']);
    config()->set('toolbox', $toolbox);

    expect(app(Greeter::class))->toBeInstanceOf(EnglishGreeter::class);
});

it('binds the default when the value is null', function (): void {
    config()->set('toolbox.greeter', null);

    expect(app(Greeter::class))->toBeInstanceOf(EnglishGreeter::class);
});

it('binds the default when the value is blank, exactly as if it were not set', function (string $blank): void {
    config()->set('toolbox.greeter', $blank);

    expect(app(Greeter::class))->toBeInstanceOf(EnglishGreeter::class);
})->with([
    'empty' => [''],
    'spaces' => ['   '],
    'a tab' => ["\t"],
    'a newline' => ["\n"],
    'mixed whitespace' => [" \t\r\n "],
]);

it('binds the implementation config names', function (): void {
    config()->set('toolbox.greeter', FrenchGreeter::class);

    expect(app(Greeter::class)->greet())->toBe('bonjour');
});

it('refuses a configured value that is not a class-string of the contract', function (mixed $configured, string $given): void {
    config()->set('toolbox.greeter', $configured);

    expect(fn (): mixed => app(Greeter::class))->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [toolbox.greeter] must be a class-string of ['.Greeter::class."], [{$given}] given.",
    );
})->with([
    'false' => [false, 'false'],
    'true' => [true, 'true'],
    'an integer' => [42, '42'],
    'an array' => [[FrenchGreeter::class], 'array'],
    'a missing class' => ['App\\Greeters\\Missing', 'App\\Greeters\\Missing'],
    'a class that is not a Greeter' => [Widget::class, Widget::class],
    'the contract itself' => [Greeter::class, Greeter::class],
]);

it('rejects a non-instantiable implementation', function (): void {
    config()->set('toolbox.greeter', AbstractGreeter::class);

    expect(fn (): mixed => app(Greeter::class))->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [toolbox.greeter] must be a class-string of ['.Greeter::class.'], ['.AbstractGreeter::class.'] given.',
    );
});

/**
 * Binds `$contract` through `bindFromConfig()` on a throwaway provider, behind a depth guard:
 * a binding that re-enters itself fails the test instead of recursing until PHP segfaults
 * and takes the whole suite down with it.
 *
 * @param  class-string  $contract
 * @param  class-string  $default
 */
function bindThroughConfig(string $contract, string $default): void
{
    $provider = new class(app()) extends PackageServiceProvider
    {
        public function configurePackage(Package $package): void
        {
            $package->name('binding_fixture');
        }

        public function bindsFromConfig(string $contract, string $default): void
        {
            $this->bindFromConfig($contract, 'binding_fixture.implementation', $default);
        }
    };

    $provider->bindsFromConfig($contract, $default);

    $depth = 0;

    app()->beforeResolving($contract, function () use (&$depth, $contract): void {
        if (++$depth > 5) {
            throw new RuntimeException("[{$contract}] re-entered its own binding {$depth} times.");
        }
    });
}

it('rejects a class contract named as its own implementation', function (string $contract, string $default, string $configured): void {
    bindThroughConfig($contract, $default);
    config()->set('binding_fixture.implementation', $configured);

    expect(fn (): mixed => app($contract))->toThrow(
        InvalidConfigurationException::class,
        "Configuration value [binding_fixture.implementation] must be a class-string of [{$contract}], [{$configured}] given.",
    );
})->with([
    'an abstract-class contract' => [Salutation::class, FormalSalutation::class, Salutation::class],
    'a concrete-class contract' => [Doorbell::class, Doorbell::class, Doorbell::class],
    'a leading backslash' => [Doorbell::class, Doorbell::class, '\\'.Doorbell::class],
    'another letter case' => [Doorbell::class, Doorbell::class, strtolower(Doorbell::class)],
]);

it('binds the implementation config names for an abstract-class contract', function (): void {
    bindThroughConfig(Salutation::class, FormalSalutation::class);
    config()->set('binding_fixture.implementation', FormalSalutation::class);

    expect(app(Salutation::class)->salute())->toBe('good day');
});

it('resolves a class contract that defaults to itself', function (mixed $unset): void {
    bindThroughConfig(Doorbell::class, Doorbell::class);
    config()->set('binding_fixture.implementation', $unset);

    $doorbell = app(Doorbell::class);

    expect($doorbell)->toBeInstanceOf(Doorbell::class)
        ->and($doorbell::class)->toBe(Doorbell::class)
        ->and($doorbell->greeter)->toBeInstanceOf(EnglishGreeter::class);
})->with(['null' => [null], 'blank' => ['  ']]);

it('swaps a self-defaulting class contract for the subclass config names', function (): void {
    bindThroughConfig(Doorbell::class, Doorbell::class);
    config()->set('binding_fixture.implementation', ChimingDoorbell::class);

    expect(app(Doorbell::class))->toBeInstanceOf(ChimingDoorbell::class);
});
