<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

describe('intBetween', function (): void {
    it('returns an in-range value', function (): void {
        config()->set('toolbox.n', 5);

        expect(Config::intBetween('toolbox.n', 1, 10, 3))->toBe(5);
    });

    it('coerces a numeric string', function (): void {
        config()->set('toolbox.n', '7');

        expect(Config::intBetween('toolbox.n', 1, 10, 3))->toBe(7);
    });

    it('falls back to the default when missing', function (): void {
        config()->set('toolbox.n', null);

        expect(Config::intBetween('toolbox.n', 1, 10, 3))->toBe(3);
    });

    it('throws when not an integer', function (): void {
        config()->set('toolbox.n', 'abc');

        Config::intBetween('toolbox.n', 1, 10, 3);
    })->throws(InvalidConfigurationException::class);

    it('throws when out of range', function (): void {
        config()->set('toolbox.n', 99);

        Config::intBetween('toolbox.n', 1, 10, 3);
    })->throws(InvalidConfigurationException::class, 'between 1 and 10');
});

describe('requireString', function (): void {
    it('returns a present string', function (): void {
        config()->set('toolbox.s', 'value');

        expect(Config::requireString('toolbox.s'))->toBe('value');
    });

    it('throws when missing', function (): void {
        config()->set('toolbox.s', null);

        Config::requireString('toolbox.s');
    })->throws(InvalidConfigurationException::class, 'required but missing');

    it('throws when blank or of the wrong type', function (): void {
        config()->set('toolbox.s', '   ');

        Config::requireString('toolbox.s');
    })->throws(InvalidConfigurationException::class, 'non-empty string');
});

describe('enumOr', function (): void {
    it('maps a backing value', function (): void {
        config()->set('toolbox.kt', 'uuid');

        expect(Config::enumOr('toolbox.kt', KeyType::class, KeyType::BigInt))->toBe(KeyType::Uuid);
    });

    it('passes through an enum instance', function (): void {
        config()->set('toolbox.kt', KeyType::Ulid);

        expect(Config::enumOr('toolbox.kt', KeyType::class, KeyType::BigInt))->toBe(KeyType::Ulid);
    });

    it('falls back for an unknown or missing value', function (): void {
        config()->set('toolbox.kt', 'nope');
        expect(Config::enumOr('toolbox.kt', KeyType::class, KeyType::BigInt))->toBe(KeyType::BigInt);

        config()->set('toolbox.kt', null);
        expect(Config::enumOr('toolbox.kt', KeyType::class, KeyType::Ulid))->toBe(KeyType::Ulid);
    });
});

describe('boolean', function (): void {
    it('parses truthy and falsy values', function (): void {
        config()->set('toolbox.b', 'true');
        expect(Config::boolean('toolbox.b'))->toBeTrue();

        config()->set('toolbox.b', '0');
        expect(Config::boolean('toolbox.b'))->toBeFalse();
    });

    it('falls back to the default', function (): void {
        config()->set('toolbox.b', null);
        expect(Config::boolean('toolbox.b', true))->toBeTrue();

        config()->set('toolbox.b', 'not-a-bool');
        expect(Config::boolean('toolbox.b', true))->toBeTrue();
    });
});
