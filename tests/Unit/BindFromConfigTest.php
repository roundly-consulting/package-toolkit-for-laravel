<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\EnglishGreeter;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\FrenchGreeter;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\Greeter;
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
    'an empty string' => ['', "''"],
    'an integer' => [42, '42'],
    'an array' => [[FrenchGreeter::class], 'array'],
    'a missing class' => ['App\\Greeters\\Missing', 'App\\Greeters\\Missing'],
    'a class that is not a Greeter' => [Widget::class, Widget::class],
    'the contract itself' => [Greeter::class, Greeter::class],
]);
