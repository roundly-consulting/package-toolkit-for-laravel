<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\CustomConfigException;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Priority;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Version;

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

describe('strictBoolean', function (): void {
    it('parses the same vocabulary as boolean()', function (mixed $value, bool $expected): void {
        config()->set('toolbox.b', $value);

        expect(Config::strictBoolean('toolbox.b'))->toBe($expected)
            ->and(Config::boolean('toolbox.b', ! $expected))->toBe($expected);
    })->with([
        [true, true], [false, false], [1, true], [0, false],
        ['true', true], ['TRUE', true], ['1', true], ['on', true], ['yes', true],
        ['false', false], ['0', false], ['off', false], ['no', false], ['', false],
    ]);

    it('falls back to the default only when the key is absent', function (): void {
        config()->set('toolbox.b', null);

        expect(Config::strictBoolean('toolbox.b', true))->toBeTrue()
            ->and(Config::strictBoolean('toolbox.b'))->toBeFalse();
    });

    it('THROWS on a typo where boolean() would silently fall back', function (mixed $value): void {
        config()->set('toolbox.b', $value);

        // boolean() is lenient — the typo reads as the default without a peep.
        expect(Config::boolean('toolbox.b', true))->toBeTrue();

        // strictBoolean() fails loudly.
        Config::strictBoolean('toolbox.b', true);
    })->with(['disabled', 'nope', 'ture', '2', ['x']])->throws(InvalidConfigurationException::class, 'must be a boolean');

    it('names the key, the offending value and the accepted spellings', function (mixed $value, string $given): void {
        config()->set('toolbox.b', $value);

        expect(fn (): bool => Config::strictBoolean('toolbox.b', true))->toThrow(
            InvalidConfigurationException::class,
            "Configuration value [toolbox.b] must be a boolean (true/false, 1/0, on/off or yes/no), [{$given}] given.",
        );
    })->with([
        'a string' => ['disabled', 'disabled'],
        'an integer' => [-1, '-1'],
        'a float' => [2.0, '2.0'],
        'an array' => [['x'], 'array'],
    ]);

    it('throws the nominated exception for a handed array', function (): void {
        expect(Config::for(['flag' => 'off'])->strictBoolean('flag', true))->toBeFalse();

        Config::for(['flag' => 'disabled'], CustomConfigException::class)->strictBoolean('flag');
    })->throws(CustomConfigException::class, '[flag] must be a boolean (true/false, 1/0, on/off or yes/no), [disabled] given.');
});

describe('enum (strict)', function (): void {
    it('maps a recognized value', function (): void {
        config()->set('toolbox.kt', 'uuid');

        expect(Config::enum('toolbox.kt', KeyType::class))->toBe(KeyType::Uuid);
    });

    it('passes through an enum instance', function (): void {
        config()->set('toolbox.kt', KeyType::Ulid);

        expect(Config::enum('toolbox.kt', KeyType::class))->toBe(KeyType::Ulid);
    });

    it('THROWS on a typo where enumOr would silently fall back', function (): void {
        config()->set('toolbox.kt', 'uudi');

        // enumOr is lenient — it downgrades to the default without a peep.
        expect(Config::enumOr('toolbox.kt', KeyType::class, KeyType::BigInt))->toBe(KeyType::BigInt);

        // enum is strict — the same typo fails loudly, listing the valid cases.
        Config::enum('toolbox.kt', KeyType::class);
    })->throws(InvalidConfigurationException::class, 'must be one of [bigint, uuid, ulid]');

    it('throws when the value is missing', function (): void {
        config()->set('toolbox.kt', null);

        Config::enum('toolbox.kt', KeyType::class);
    })->throws(InvalidConfigurationException::class, 'required but missing');
});

describe('array validation via for()', function (): void {
    it('validates the array it was handed, not the global repository', function (): void {
        // The classic bypass: repository holds a safe value, the handed array
        // holds an out-of-range one. Reading the repository would ACCEPT 60;
        // validating the array must REJECT 99999.
        config()->set('passkeys.timeout', 60);

        Config::for(['timeout' => 99999])->intBetween('timeout', 1, 300, 60);
    })->throws(InvalidConfigurationException::class, 'between 1 and 300');

    it('reads values out of the handed array, including dot notation', function (): void {
        $config = ['timeout' => '120', 'attestation' => 'uuid', 'nested' => ['flag' => 'true']];

        $validator = Config::for($config);

        expect($validator->intBetween('timeout', 1, 300, 60))->toBe(120)
            ->and($validator->enum('attestation', KeyType::class))->toBe(KeyType::Uuid)
            ->and($validator->boolean('nested.flag'))->toBeTrue();
    });

    it('rejects a strict enum typo in a handed array', function (): void {
        Config::for(['attestation' => 'bassic'])->enum('attestation', KeyType::class);
    })->throws(InvalidConfigurationException::class, 'must be one of');

    it('falls back to the default for a missing optional key', function (): void {
        expect(Config::for([])->intBetween('timeout', 1, 300, 60))->toBe(60)
            ->and(Config::for([])->enumOr('attestation', KeyType::class, KeyType::BigInt))->toBe(KeyType::BigInt)
            ->and(Config::for([])->boolean('flag', true))->toBeTrue();
    });
});

describe('nominated exception class', function (): void {
    it('throws the package exception from an array validator', function (): void {
        Config::for(['timeout' => 99999], CustomConfigException::class)
            ->intBetween('timeout', 1, 300, 60);
    })->throws(CustomConfigException::class, 'between 1 and 300');

    it('throws the package exception from a repository validator', function (): void {
        config()->set('passkeys.attestation', 'nope');

        Config::using(CustomConfigException::class)->enum('passkeys.attestation', KeyType::class);
    })->throws(CustomConfigException::class, 'must be one of');

    it('preserves the toolkit exception message when none is nominated', function (): void {
        Config::for(['timeout' => 99999])->intBetween('timeout', 1, 300, 60);
    })->throws(InvalidConfigurationException::class, 'between 1 and 300');
});

describe('enum backing-type coercion', function (): void {
    // strict_types makes Priority::tryFrom('2') a TypeError, and every env value is a
    // string — so an int-backed enum read from env must be coerced, never passed raw.
    it('coerces a numeric env string into an int-backed case', function (string $value, Priority $expected): void {
        config()->set('toolbox.priority', $value);

        expect(Config::enum('toolbox.priority', Priority::class))->toBe($expected)
            ->and(Config::enumOr('toolbox.priority', Priority::class, Priority::Low))->toBe($expected)
            ->and(Config::for(['priority' => $value])->enum('priority', Priority::class))->toBe($expected);
    })->with([
        ['2', Priority::High],
        ['-1', Priority::Below],
        [' 1 ', Priority::Low],
    ]);

    it('accepts a native int for an int-backed enum', function (): void {
        config()->set('toolbox.priority', 2);

        expect(Config::enum('toolbox.priority', Priority::class))->toBe(Priority::High);
    });

    it('coerces an int into a string-backed case', function (): void {
        config()->set('toolbox.version', 2);

        expect(Config::enum('toolbox.version', Version::class))->toBe(Version::Two)
            ->and(Config::enumOr('toolbox.version', Version::class, Version::One))->toBe(Version::Two);
    });

    it('lets enumOr fall back instead of crashing on a mismatched type', function (mixed $value): void {
        config()->set('toolbox.priority', $value);
        config()->set('toolbox.kt', $value);

        expect(Config::enumOr('toolbox.priority', Priority::class, Priority::Low))->toBe(Priority::Low)
            ->and(Config::enumOr('toolbox.kt', KeyType::class, KeyType::Ulid))->toBe(KeyType::Ulid);
    })->with([
        'unknown int' => [1],
        'non-numeric string' => ['high'],
        'malformed number' => ['--2'],
        'decimal string' => ['2.0'],
        'float' => [2.0],
        'bool' => [true],
        'overflowing number' => ['99999999999999999999'],
    ]);

    it('throws the configuration exception, never a TypeError, for a mismatched type', function (mixed $value): void {
        config()->set('toolbox.priority', $value);

        expect(fn () => Config::enum('toolbox.priority', Priority::class))
            ->toThrow(InvalidConfigurationException::class, 'must be one of [1, 2, -1]');
    })->with([
        ['high'],
        ['2.0'],
        ['--2'],
        [2.5],
        [['2']],
    ]);

    it('throws the configuration exception for an int no string-backed case matches', function (): void {
        config()->set('toolbox.kt', 1);

        Config::enum('toolbox.kt', KeyType::class);
    })->throws(InvalidConfigurationException::class, 'must be one of [bigint, uuid, ulid]');

    it('throws the nominated exception for a mismatched type', function (): void {
        Config::for(['priority' => 'x'], CustomConfigException::class)->enum('priority', Priority::class);
    })->throws(CustomConfigException::class, 'must be one of [1, 2, -1]');
});

describe('intBetween rejects malformed numbers', function (): void {
    it('throws instead of silently coercing', function (string $value): void {
        config()->set('toolbox.n', $value);

        Config::intBetween('toolbox.n', -10, 10, 3);
    })->throws(InvalidConfigurationException::class, 'must be an integer')->with([
        ['--5'],
        ['-'],
        ['5-'],
        ['-5-'],
        ['1.5'],
        ['99999999999999999999'],
    ]);

    it('accepts a signed integer string', function (): void {
        config()->set('toolbox.n', '-5');

        expect(Config::intBetween('toolbox.n', -10, 10, 3))->toBe(-5);
    });
});
