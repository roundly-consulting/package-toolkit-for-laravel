<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\PackageConfigurationException;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\AbstractGreeter;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\ChimingDoorbell;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\Doorbell;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\EnglishGreeter;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\FrenchGreeter;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\Greeter;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\Widget;

/*
 * The readers 2.1 added. Each one follows the toolkit's contract: not set (absent, null or
 * blank) is the default, anything else is validated and THROWS rather than falling back.
 */

describe('optionalString', function (): void {
    it('returns a present string as given, never trimmed', function (): void {
        config()->set('toolbox.connection', ' redis ');

        expect(Config::optionalString('toolbox.connection'))->toBe(' redis ')
            ->and(Config::for(['connection' => 'sqs'])->optionalString('connection'))->toBe('sqs');
    });

    it('returns null when the key is not set', function (mixed $blank): void {
        config()->set('toolbox.connection', $blank);

        expect(Config::optionalString('toolbox.connection'))->toBeNull()
            ->and(Config::optionalString('toolbox.never_set'))->toBeNull();
    })->with(['null' => [null], 'empty' => [''], 'whitespace' => ['   ']]);

    it('THROWS on a non-string, naming the value', function (mixed $value, string $given): void {
        config()->set('toolbox.connection', $value);

        expect(fn (): ?string => Config::optionalString('toolbox.connection'))->toThrow(
            InvalidConfigurationException::class,
            "Configuration value [toolbox.connection] must be a non-empty string, [{$given}] given.",
        );
    })->with([
        'an int' => [5, '5'],
        'false' => [false, 'false'],
        'an array' => [['redis'], 'array'],
    ]);
});

describe('integerList', function (): void {
    it('reads an array of ints or integer strings, or an env comma list', function (mixed $value, array $expected): void {
        config()->set('toolbox.weekdays', $value);

        expect(Config::integerList('toolbox.weekdays', [1], min: 1, max: 7))->toBe($expected);
    })->with([
        'ints' => [[1, 2, 3], [1, 2, 3]],
        'integer strings' => [['6', ' 7 '], [6, 7]],
        'a comma list' => ['1, 2,3', [1, 2, 3]],
        'empty items dropped' => ['1,,5, ', [1, 5]],
        'a keyed array, re-indexed' => [['mon' => 1, 'fri' => '5'], [1, 5]],
        'duplicates kept' => ['1,1', [1, 1]],
    ]);

    it('returns the default when the key is not set or yields no items', function (mixed $value): void {
        config()->set('toolbox.weekdays', $value);

        expect(Config::integerList('toolbox.weekdays', [1, 2, 3, 4, 5], min: 1, max: 7))->toBe([1, 2, 3, 4, 5])
            ->and(Config::integerList('toolbox.never_set', [9]))->toBe([9]);
    })->with(['null' => [null], 'blank' => [' '], 'only commas' => [','], 'an empty array' => [[]], 'blank items' => [['', ' ']]]);

    it('reads without bounds when none are given', function (): void {
        config()->set('toolbox.offsets', '-5,0,99999');

        expect(Config::integerList('toolbox.offsets', []))->toBe([-5, 0, 99999]);
    });

    it('THROWS on a value that is not a list, naming it', function (mixed $value, string $given): void {
        config()->set('toolbox.weekdays', $value);

        expect(fn (): array => Config::integerList('toolbox.weekdays', [1]))->toThrow(
            InvalidConfigurationException::class,
            "Configuration value [toolbox.weekdays] must be a list of integers (an array or a comma-separated string), [{$given}] given.",
        );
    })->with(['an int' => [5, '5'], 'true' => [true, 'true'], 'a float' => [1.5, '1.5']]);

    it('THROWS on an item that is not an integer, naming the item', function (mixed $value, string $given): void {
        config()->set('toolbox.weekdays', $value);

        expect(fn (): array => Config::integerList('toolbox.weekdays', [1]))->toThrow(
            InvalidConfigurationException::class,
            "Configuration value [toolbox.weekdays] must contain only integer items, [{$given}] given.",
        );
    })->with([
        'a word' => ['1,two', 'two'],
        'a decimal' => ['1.5', '1.5'],
        'an explicit plus' => ['+3', '+3'],
        'a float item' => [[1, 2.0], '2.0'],
        'a bool item' => [[true], 'true'],
        'a nested array' => [[[1]], 'array'],
        'a null item' => [[1, null], 'null'],
    ]);

    it('THROWS on an item out of range, naming the bound and the item', function (mixed $value, string $given): void {
        config()->set('toolbox.weekdays', $value);

        expect(fn (): array => Config::integerList('toolbox.weekdays', [1], min: 1, max: 7))->toThrow(
            InvalidConfigurationException::class,
            "Configuration value [toolbox.weekdays] must contain only items between 1 and 7, [{$given}] given.",
        );
    })->with(['above' => ['1,8', '8'], 'below' => [[0, 1], '0']]);

    it('bounds the default too', function (): void {
        expect(fn (): array => Config::integerList('toolbox.never_set', [0], min: 1))->toThrow(
            InvalidConfigurationException::class,
            'Configuration value [toolbox.never_set] must contain only items at least 1, [0] given.',
        )->and(fn (): array => Config::integerList('toolbox.never_set', [10], max: 7))->toThrow(
            InvalidConfigurationException::class,
            'Configuration value [toolbox.never_set] must contain only items at most 7, [10] given.',
        );
    });

    it('validates a handed array the same way', function (): void {
        expect(Config::for(['days' => '2,3'])->integerList('days', [1], 1, 7))->toBe([2, 3])
            ->and(fn (): array => Config::for(['days' => 'x'])->integerList('days', [1]))
            ->toThrow(InvalidConfigurationException::class, 'Configuration value [days] must contain only integer items, [x] given.');
    });
});

describe('optionalInteger', function (): void {
    it('reads an integer like integer()', function (mixed $value, int $expected): void {
        config()->set('toolbox.rate', $value);

        expect(Config::optionalInteger('toolbox.rate', 60, min: 1, max: 1000))->toBe($expected);
    })->with([[5, 5], ['30', 30], [' 7 ', 7]]);

    it('returns null for an off word, case-insensitive and trimmed', function (string $off): void {
        config()->set('toolbox.rate', $off);

        expect(Config::optionalInteger('toolbox.rate', 60, min: 1))->toBeNull();
    })->with(['off', 'OFF', ' Off ']);

    it('takes its own off words', function (): void {
        config()->set('toolbox.rate', 'none');

        expect(Config::optionalInteger('toolbox.rate', 60, offWords: ['off', 'none', 'null']))->toBeNull()
            ->and(fn (): ?int => Config::optionalInteger('toolbox.rate', 60))->toThrow(
                InvalidConfigurationException::class,
                'Configuration value [toolbox.rate] must be an integer or one of [off], [none] given.',
            );
    });

    it('returns the default when the key is not set, a null default included', function (mixed $blank): void {
        config()->set('toolbox.rate', $blank);

        expect(Config::optionalInteger('toolbox.rate', 60))->toBe(60)
            ->and(Config::optionalInteger('toolbox.rate', null, min: 1))->toBeNull()
            ->and(Config::optionalInteger('toolbox.never_set', 9))->toBe(9);
    })->with(['null' => [null], 'empty' => [''], 'whitespace' => ['  ']]);

    it('THROWS on anything but an integer or an off word, listing the off words', function (mixed $value, string $given): void {
        config()->set('toolbox.rate', $value);

        expect(fn (): ?int => Config::optionalInteger('toolbox.rate', 60, offWords: ['off', 'none']))->toThrow(
            InvalidConfigurationException::class,
            "Configuration value [toolbox.rate] must be an integer or one of [off, none], [{$given}] given.",
        );
    })->with([
        'a word' => ['never', 'never'],
        'a decimal' => ['5.5', '5.5'],
        'false' => [false, 'false'],
        'a float' => [5.0, '5.0'],
        'an array' => [['off'], 'array'],
    ]);

    it('says "an integer" alone when no off word is accepted', function (): void {
        config()->set('toolbox.rate', 'off');

        expect(fn (): ?int => Config::optionalInteger('toolbox.rate', 60, offWords: []))->toThrow(
            InvalidConfigurationException::class,
            'Configuration value [toolbox.rate] must be an integer, [off] given.',
        );
    });

    it('THROWS on a value or default out of range', function (): void {
        config()->set('toolbox.rate', '0');

        expect(fn (): ?int => Config::optionalInteger('toolbox.rate', 60, min: 1, max: 100))->toThrow(
            InvalidConfigurationException::class,
            'Configuration value [toolbox.rate] must be between 1 and 100, [0] given.',
        )->and(fn (): ?int => Config::optionalInteger('toolbox.never_set', 500, max: 100))->toThrow(
            InvalidConfigurationException::class,
            'Configuration value [toolbox.never_set] must be at most 100, [500] given.',
        );
    });

    it('validates a handed array the same way', function (): void {
        expect(Config::for(['rate' => 'off'])->optionalInteger('rate', 60))->toBeNull()
            ->and(Config::for(['rate' => '10'])->optionalInteger('rate', 60))->toBe(10);
    });
});

describe('implementation', function (): void {
    it('returns the configured implementation of the contract', function (): void {
        config()->set('toolbox.greeter', FrenchGreeter::class);

        expect(Config::implementation('toolbox.greeter', Greeter::class, EnglishGreeter::class))->toBe(FrenchGreeter::class);
    });

    it('returns the class as declared, whatever its spelling', function (): void {
        config()->set('toolbox.greeter', '\\'.strtolower(FrenchGreeter::class));

        expect(Config::implementation('toolbox.greeter', Greeter::class))->toBe(FrenchGreeter::class);
    });

    it('accepts a subclass of a concrete contract', function (): void {
        config()->set('toolbox.doorbell', ChimingDoorbell::class);

        expect(Config::implementation('toolbox.doorbell', Doorbell::class, Doorbell::class))->toBe(ChimingDoorbell::class);
    });

    it('returns the default, or null, when the key is not set', function (mixed $blank): void {
        config()->set('toolbox.greeter', $blank);

        expect(Config::implementation('toolbox.greeter', Greeter::class, EnglishGreeter::class))->toBe(EnglishGreeter::class)
            ->and(Config::implementation('toolbox.greeter', Greeter::class))->toBeNull()
            ->and(Config::implementation('toolbox.doorbell', Doorbell::class, Doorbell::class))->toBe(Doorbell::class);
    })->with(['null' => [null], 'empty' => [''], 'whitespace' => [' ']]);

    it('THROWS notAnImplementation on anything but an instantiable implementation', function (mixed $value, string $given): void {
        config()->set('toolbox.greeter', $value);

        expect(fn (): ?string => Config::implementation('toolbox.greeter', Greeter::class, EnglishGreeter::class))->toThrow(
            InvalidConfigurationException::class,
            'Configuration value [toolbox.greeter] must be a class-string of ['.Greeter::class."], [{$given}] given.",
        );
    })->with([
        'a missing class' => ['App\\Greeters\\Nope', 'App\\Greeters\\Nope'],
        'a class of the wrong type' => [Widget::class, Widget::class],
        'an abstract class' => [AbstractGreeter::class, AbstractGreeter::class],
        'the interface itself' => [Greeter::class, Greeter::class],
        'a padded name' => [' '.FrenchGreeter::class, ' '.FrenchGreeter::class],
        'an int' => [5, '5'],
        'false' => [false, 'false'],
        'an object' => [new FrenchGreeter, FrenchGreeter::class],
    ]);

    it('refuses a concrete contract named as the configured value', function (): void {
        config()->set('toolbox.doorbell', Doorbell::class);

        expect(fn (): ?string => Config::implementation('toolbox.doorbell', Doorbell::class, Doorbell::class))->toThrow(
            InvalidConfigurationException::class,
            'Configuration value [toolbox.doorbell] must be a class-string of ['.Doorbell::class.'], ['.Doorbell::class.'] given.',
        );
    });

    it('throws the nominated exception', function (): void {
        config()->set('toolbox.greeter', Widget::class);

        expect(fn (): ?string => Config::using(PackageConfigurationException::class)->implementation('toolbox.greeter', Greeter::class))
            ->toThrow(PackageConfigurationException::class, 'must be a class-string of');
    });
});

describe('stringMap', function (): void {
    it('reads a map of names to strings, values as given', function (): void {
        config()->set('toolbox.abilities', ['view' => 'workflows.view', 'start' => ' start ']);

        expect(Config::stringMap('toolbox.abilities'))->toBe(['view' => 'workflows.view', 'start' => ' start '])
            ->and(Config::for(['a' => ['x' => 'y']])->stringMap('a'))->toBe(['x' => 'y']);
    });

    it('returns an empty map when the key is not set', function (mixed $blank): void {
        config()->set('toolbox.abilities', $blank);

        expect(Config::stringMap('toolbox.abilities'))->toBe([])
            ->and(Config::stringMap('toolbox.never_set'))->toBe([]);
    })->with(['null' => [null], 'empty' => [''], 'an empty array' => [[]]]);

    it('THROWS on a value that is not a map', function (mixed $value, string $given): void {
        config()->set('toolbox.abilities', $value);

        expect(fn (): array => Config::stringMap('toolbox.abilities'))->toThrow(
            InvalidConfigurationException::class,
            "Configuration value [toolbox.abilities] must be a map of names to non-empty strings, [{$given}] given.",
        );
    })->with([
        'a string' => ['view', 'view'],
        'an int' => [5, '5'],
        'a list' => [['view'], '0'],
        'a blank name' => [[' ' => 'view'], ' '],
    ]);

    it('THROWS on a bad value, naming the entry', function (mixed $value, string $given): void {
        config()->set('toolbox.abilities', ['view' => 'ok', 'start' => $value]);

        expect(fn (): array => Config::stringMap('toolbox.abilities'))->toThrow(
            InvalidConfigurationException::class,
            "Configuration value [toolbox.abilities.start] must be a non-empty string, [{$given}] given.",
        );
    })->with([
        'empty' => ['', "''"],
        'blank' => ['  ', '  '],
        'an int' => [5, '5'],
        'null' => [null, 'null'],
        'an array' => [['x'], 'array'],
    ]);
});

it('throws a package exception from every new reader', function (Closure $read): void {
    expect($read)->toThrow(PackageConfigurationException::class, 'Configuration value [bad]');
})->with([
    'optionalString' => [fn () => Config::for(['bad' => 1], PackageConfigurationException::class)->optionalString('bad')],
    'integerList' => [fn () => Config::for(['bad' => 'x'], PackageConfigurationException::class)->integerList('bad', [])],
    'optionalInteger' => [fn () => Config::for(['bad' => 'x'], PackageConfigurationException::class)->optionalInteger('bad', null)],
    'implementation' => [fn () => Config::for(['bad' => 'x'], PackageConfigurationException::class)->implementation('bad', Greeter::class)],
    'stringMap' => [fn () => Config::for(['bad' => 'x'], PackageConfigurationException::class)->stringMap('bad')],
]);
