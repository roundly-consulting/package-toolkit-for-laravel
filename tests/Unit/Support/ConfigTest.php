<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\PackageToolkit\Support\ConfigValidator;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\CustomConfigException;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Priority;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Version;

describe('integer', function (): void {
    it('reads an int or a canonical integer string', function (mixed $value, int $expected): void {
        config()->set('toolbox.n', $value);

        expect(Config::integer('toolbox.n', 3))->toBe($expected);
    })->with([
        [5, 5], [0, 0], [-5, -5], ['30', 30], ['-5', -5], [' 30 ', 30], ["30\n", 30], ['007', 7], ['-0', 0],
    ]);

    it('falls back to the default only when the key is absent or null', function (): void {
        config()->set('toolbox.n', null);

        expect(Config::integer('toolbox.n', 3))->toBe(3)
            ->and(Config::integer('toolbox.never_set', 9))->toBe(9)
            ->and(Config::for([])->integer('timeout', 60, min: 1, max: 300))->toBe(60);
    });

    it('THROWS on anything but an integer instead of reading the default', function (mixed $value, string $given): void {
        config()->set('toolbox.n', $value);

        expect(fn (): int => Config::integer('toolbox.n', 3))->toThrow(
            InvalidConfigurationException::class,
            "Configuration value [toolbox.n] must be an integer, [{$given}] given.",
        );
    })->with([
        'a word' => ['five', 'five'],
        'a decimal string' => ['5.5', '5.5'],
        'trailing junk' => ['5abc', '5abc'],
        'an exponent' => ['1e3', '1e3'],
        'hex' => ['0x10', '0x10'],
        'an explicit plus' => ['+5', '+5'],
        'a double minus' => ['--5', '--5'],
        'a lone minus' => ['-', '-'],
        'a trailing minus' => ['5-', '5-'],
        'an inner space' => ['1 000', '1 000'],
        'an overflow' => ['99999999999999999999', '99999999999999999999'],
        'a float' => [5.0, '5.0'],
        'true' => [true, 'true'],
        'false' => [false, 'false'],
        'an array' => [[5], 'array'],
    ]);

    it('reads a blank value as not set, so the default applies', function (string $blank): void {
        config()->set('toolbox.n', $blank);

        expect(Config::integer('toolbox.n', 3))->toBe(3)
            ->and(Config::integer('toolbox.n', 7, min: 1, max: 10))->toBe(7)
            ->and(Config::for(['n' => $blank])->integer('n', 9))->toBe(9);
    })->with([
        'empty' => [''],
        'spaces' => ['   '],
        'a tab' => ["\t"],
        'a newline' => ["\n"],
        'mixed whitespace' => [" \t\r\n "],
    ]);

    it('range-checks the default it falls back to for a blank value', function (): void {
        config()->set('toolbox.n', '');

        Config::integer('toolbox.n', 0, min: 1, max: 10);
    })->throws(InvalidConfigurationException::class, 'Configuration value [toolbox.n] must be between 1 and 10, [0] given.');

    it('range-checks the value, naming the bound and the value', function (mixed $value, ?int $min, ?int $max, string $message): void {
        config()->set('toolbox.n', $value);

        expect(fn (): int => Config::integer('toolbox.n', 3, $min, $max))->toThrow(
            InvalidConfigurationException::class,
            "Configuration value [toolbox.n] {$message}",
        );
    })->with([
        'above a closed range' => [99, 1, 10, 'must be between 1 and 10, [99] given.'],
        'below a closed range' => ['0', 1, 10, 'must be between 1 and 10, [0] given.'],
        'below a floor' => [-1, 0, null, 'must be at least 0, [-1] given.'],
        'above a ceiling' => ['500', null, 100, 'must be at most 100, [500] given.'],
    ]);

    it('accepts the bounds themselves', function (): void {
        config()->set('toolbox.n', '10');

        expect(Config::integer('toolbox.n', 3, min: 1, max: 10))->toBe(10)
            ->and(Config::integer('toolbox.n', 3, min: 10))->toBe(10)
            ->and(Config::integer('toolbox.n', 3, max: 10))->toBe(10);
    });

    it('range-checks the default too', function (): void {
        Config::integer('toolbox.never_set', 0, min: 1, max: 10);
    })->throws(InvalidConfigurationException::class, 'Configuration value [toolbox.never_set] must be between 1 and 10, [0] given.');

    it('has a single strict integer reader', function (): void {
        expect(method_exists(Config::class, 'intBetween'))->toBeFalse()
            ->and(method_exists(ConfigValidator::class, 'intBetween'))->toBeFalse();
    });
});

describe('requireString', function (): void {
    it('returns a present string', function (): void {
        config()->set('toolbox.s', 'value');

        expect(Config::requireString('toolbox.s'))->toBe('value');
    });

    it('throws when missing', function (): void {
        config()->set('toolbox.s', null);

        Config::requireString('toolbox.s');
    })->throws(InvalidConfigurationException::class, 'Configuration value [toolbox.s] is required but missing.');

    it('reports a blank value as missing, exactly like an absent key', function (string $blank): void {
        config()->set('toolbox.s', $blank);

        expect(fn (): string => Config::requireString('toolbox.s'))->toThrow(
            InvalidConfigurationException::class,
            'Configuration value [toolbox.s] is required but missing.',
        )->and(fn (): string => Config::for(['s' => $blank])->requireString('s'))->toThrow(
            InvalidConfigurationException::class,
            'Configuration value [s] is required but missing.',
        );
    })->with([
        'empty' => [''],
        'spaces' => ['   '],
        'a tab' => ["\t"],
        'a newline' => ["\n"],
        'mixed whitespace' => [" \t\r\n "],
    ]);

    it('throws when of the wrong type, naming the value', function (mixed $value, string $given): void {
        config()->set('toolbox.s', $value);

        expect(fn (): string => Config::requireString('toolbox.s'))->toThrow(
            InvalidConfigurationException::class,
            "Configuration value [toolbox.s] must be a non-empty string, [{$given}] given.",
        );
    })->with([
        'an integer' => [5, '5'],
        'false' => [false, 'false'],
        'an array' => [['x'], 'array'],
    ]);
});

describe('enum', function (): void {
    it('maps a backing value or passes an instance through', function (): void {
        config()->set('toolbox.kt', 'uuid');
        expect(Config::enum('toolbox.kt', KeyType::class))->toBe(KeyType::Uuid);

        config()->set('toolbox.kt', KeyType::Ulid);
        expect(Config::enum('toolbox.kt', KeyType::class, KeyType::BigInt))->toBe(KeyType::Ulid);
    });

    it('returns the default only when the key is absent or null', function (): void {
        config()->set('toolbox.kt', null);

        expect(Config::enum('toolbox.kt', KeyType::class, KeyType::Ulid))->toBe(KeyType::Ulid)
            ->and(Config::enum('toolbox.never_set', KeyType::class, KeyType::Uuid))->toBe(KeyType::Uuid)
            ->and(Config::for([])->enum('attestation', KeyType::class, KeyType::BigInt))->toBe(KeyType::BigInt);
    });

    it('throws when the key is absent and there is no default', function (): void {
        config()->set('toolbox.kt', null);

        Config::enum('toolbox.kt', KeyType::class);
    })->throws(InvalidConfigurationException::class, 'Configuration value [toolbox.kt] is required but missing.');

    it('THROWS on an unknown value even when a default is given', function (mixed $value, string $given): void {
        config()->set('toolbox.kt', $value);

        expect(fn (): KeyType => Config::enum('toolbox.kt', KeyType::class, KeyType::BigInt))->toThrow(
            InvalidConfigurationException::class,
            "Configuration value [toolbox.kt] must be one of [bigint, uuid, ulid], [{$given}] given.",
        );
    })->with([
        'a typo' => ['uudi', 'uudi'],
        'a different case' => ['UUID', 'UUID'],
        'padded' => [' uuid', ' uuid'],
        'an int' => [1, '1'],
        'false' => [false, 'false'],
        'another enum' => [Priority::High, Priority::class],
    ]);

    it('reads a blank value as not set: the default, or missing without one', function (string $blank): void {
        config()->set('toolbox.kt', $blank);

        expect(Config::enum('toolbox.kt', KeyType::class, KeyType::Ulid))->toBe(KeyType::Ulid)
            ->and(Config::for(['kt' => $blank])->enum('kt', KeyType::class, KeyType::Uuid))->toBe(KeyType::Uuid)
            ->and(fn (): KeyType => Config::enum('toolbox.kt', KeyType::class))->toThrow(
                InvalidConfigurationException::class,
                'Configuration value [toolbox.kt] is required but missing.',
            );
    })->with([
        'empty' => [''],
        'spaces' => ['   '],
        'a tab' => ["\t"],
        'a newline' => ["\n"],
        'mixed whitespace' => [" \t\r\n "],
    ]);

    it('has a single strict enum reader', function (): void {
        expect(method_exists(Config::class, 'enumOr'))->toBeFalse()
            ->and(method_exists(ConfigValidator::class, 'enumOr'))->toBeFalse();
    });
});

describe('oneOf', function (): void {
    it('returns an allowed value', function (): void {
        config()->set('toolbox.driver', 'redis');

        expect(Config::oneOf('toolbox.driver', ['array', 'redis', 'database'], 'array'))->toBe('redis');
    });

    it('returns the default only when the key is absent or null', function (): void {
        config()->set('toolbox.driver', null);

        expect(Config::oneOf('toolbox.driver', ['array', 'redis'], 'array'))->toBe('array')
            ->and(Config::for([])->oneOf('driver', ['array', 'redis'], 'redis'))->toBe('redis');
    });

    it('THROWS on anything outside the vocabulary, listing it', function (mixed $value, string $given): void {
        config()->set('toolbox.driver', $value);

        expect(fn (): string => Config::oneOf('toolbox.driver', ['array', 'redis'], 'array'))->toThrow(
            InvalidConfigurationException::class,
            "Configuration value [toolbox.driver] must be one of [array, redis], [{$given}] given.",
        );
    })->with([
        'a typo' => ['reddis', 'reddis'],
        'a different case' => ['Redis', 'Redis'],
        'an int' => [1, '1'],
        'an array' => [['redis'], 'array'],
    ]);

    it('reads a blank value as not set, so the default applies', function (string $blank): void {
        config()->set('toolbox.driver', $blank);

        expect(Config::oneOf('toolbox.driver', ['array', 'redis'], 'redis'))->toBe('redis')
            ->and(Config::for(['driver' => $blank])->oneOf('driver', ['array', 'redis'], 'array'))->toBe('array');
    })->with([
        'empty' => [''],
        'spaces' => ['   '],
        'a tab' => ["\t"],
        'a newline' => ["\n"],
        'mixed whitespace' => [" \t\r\n "],
    ]);

    it('rejects a default outside the vocabulary', function (): void {
        Config::oneOf('toolbox.never_set', ['array', 'redis'], 'file');
    })->throws(InvalidConfigurationException::class, 'Configuration value [toolbox.never_set] must be one of [array, redis], [file] given.');

    it('throws the nominated exception', function (): void {
        Config::using(CustomConfigException::class)->oneOf('toolbox.never_set', ['a'], 'b');
    })->throws(CustomConfigException::class, 'must be one of [a], [b] given.');
});

describe('boolean', function (): void {
    it('parses the env-style vocabulary', function (mixed $value, bool $expected): void {
        config()->set('toolbox.b', $value);

        expect(Config::boolean('toolbox.b', ! $expected))->toBe($expected);
    })->with([
        [true, true], [false, false], [1, true], [0, false], [1.0, true], [0.0, false],
        ['true', true], ['TRUE', true], ['1', true], ['on', true], ['yes', true], [' Yes ', true], ["true\n", true],
        ['false', false], ['0', false], ['off', false], ['no', false], ['OFF', false],
    ]);

    it('reads a blank value as not set, so the default applies, never false', function (string $blank): void {
        config()->set('toolbox.b', $blank);

        expect(Config::boolean('toolbox.b', true))->toBeTrue()
            ->and(Config::boolean('toolbox.b', false))->toBeFalse()
            ->and(Config::boolean('toolbox.b'))->toBeFalse()
            ->and(Config::for(['b' => $blank])->boolean('b', true))->toBeTrue()
            ->and(Config::using(CustomConfigException::class)->boolean('toolbox.b', true))->toBeTrue();
    })->with([
        'empty' => [''],
        'spaces' => ['   '],
        'a tab' => ["\t"],
        'a newline' => ["\n"],
        'mixed whitespace' => [" \t\r\n "],
    ]);

    it('falls back to the default only when the key is absent or null', function (): void {
        config()->set('toolbox.b', null);

        expect(Config::boolean('toolbox.b', true))->toBeTrue()
            ->and(Config::boolean('toolbox.b'))->toBeFalse()
            ->and(Config::boolean('toolbox.never_set', true))->toBeTrue()
            ->and(Config::for([])->boolean('flag', true))->toBeTrue()
            ->and(Config::for(['flag' => null])->boolean('flag', true))->toBeTrue();
    });

    it('THROWS on a present but unparseable value instead of reading the default', function (mixed $value): void {
        config()->set('toolbox.b', $value);

        Config::boolean('toolbox.b', true);
    })->with(['disabled', 'enabled', 'nope', 'ture', 'y', 'n', '2', 2, -1, 1.5, ['x']])
        ->throws(InvalidConfigurationException::class, 'must be a boolean');

    it('names the key, the offending value and the accepted spellings', function (mixed $value, string $given): void {
        config()->set('toolbox.b', $value);

        expect(fn (): bool => Config::boolean('toolbox.b', true))->toThrow(
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
        expect(Config::for(['flag' => 'off'])->boolean('flag', true))->toBeFalse();

        Config::for(['flag' => 'disabled'], CustomConfigException::class)->boolean('flag');
    })->throws(CustomConfigException::class, '[flag] must be a boolean (true/false, 1/0, on/off or yes/no), [disabled] given.');

    it('throws the nominated exception for a repository read', function (): void {
        config()->set('passkeys.enabled', 'maybe');

        Config::using(CustomConfigException::class)->boolean('passkeys.enabled', true);
    })->throws(CustomConfigException::class, '[passkeys.enabled] must be a boolean');

    it('has a single boolean reader, with no separate strict twin', function (): void {
        expect(method_exists(Config::class, 'strictBoolean'))->toBeFalse()
            ->and(method_exists(ConfigValidator::class, 'strictBoolean'))->toBeFalse();
    });
});

describe('array validation via for()', function (): void {
    it('validates the array it was handed, not the global repository', function (): void {
        // The classic bypass: repository holds a safe value, the handed array
        // holds an out-of-range one. Reading the repository would ACCEPT 60;
        // validating the array must REJECT 99999.
        config()->set('passkeys.timeout', 60);

        Config::for(['timeout' => 99999])->integer('timeout', 60, min: 1, max: 300);
    })->throws(InvalidConfigurationException::class, 'between 1 and 300');

    it('reads values out of the handed array, including dot notation', function (): void {
        $config = ['timeout' => '120', 'attestation' => 'uuid', 'nested' => ['flag' => 'true'], 'driver' => 'redis'];

        $validator = Config::for($config);

        expect($validator->integer('timeout', 60, min: 1, max: 300))->toBe(120)
            ->and($validator->enum('attestation', KeyType::class))->toBe(KeyType::Uuid)
            ->and($validator->boolean('nested.flag'))->toBeTrue()
            ->and($validator->oneOf('driver', ['array', 'redis'], 'array'))->toBe('redis');
    });

    it('rejects a strict enum typo in a handed array', function (): void {
        Config::for(['attestation' => 'bassic'])->enum('attestation', KeyType::class, KeyType::BigInt);
    })->throws(InvalidConfigurationException::class, 'must be one of');
});

describe('nominated exception class', function (): void {
    it('throws the package exception from an array validator', function (): void {
        Config::for(['timeout' => 99999], CustomConfigException::class)
            ->integer('timeout', 60, min: 1, max: 300);
    })->throws(CustomConfigException::class, 'between 1 and 300');

    it('throws the package exception from a repository validator', function (): void {
        config()->set('passkeys.attestation', 'nope');

        Config::using(CustomConfigException::class)->enum('passkeys.attestation', KeyType::class);
    })->throws(CustomConfigException::class, 'must be one of');

    it('preserves the toolkit exception message when none is nominated', function (): void {
        Config::for(['timeout' => 99999])->integer('timeout', 60, min: 1, max: 300);
    })->throws(InvalidConfigurationException::class, 'Configuration value [timeout] must be between 1 and 300, [99999] given.');
});

describe('enum backing-type coercion', function (): void {
    // strict_types makes Priority::tryFrom('2') a TypeError, and every env value is a
    // string — so an int-backed enum read from env must be coerced, never passed raw.
    it('coerces a canonical integer string into an int-backed case', function (string $value, Priority $expected): void {
        config()->set('toolbox.priority', $value);

        expect(Config::enum('toolbox.priority', Priority::class))->toBe($expected)
            ->and(Config::enum('toolbox.priority', Priority::class, Priority::Low))->toBe($expected)
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
            ->and(Config::enum('toolbox.version', Version::class, Version::One))->toBe(Version::Two);
    });

    it('throws the configuration exception, never a TypeError or the default, for a mismatched type', function (mixed $value, string $given): void {
        config()->set('toolbox.priority', $value);

        expect(fn (): Priority => Config::enum('toolbox.priority', Priority::class, Priority::Low))
            ->toThrow(InvalidConfigurationException::class, "must be one of [1, 2, -1], [{$given}] given.");
    })->with([
        'unknown int' => [3, '3'],
        'non-numeric string' => ['high', 'high'],
        'malformed number' => ['--2', '--2'],
        'decimal string' => ['2.0', '2.0'],
        'explicit plus' => ['+2', '+2'],
        'float' => [2.5, '2.5'],
        'bool' => [true, 'true'],
        'overflowing number' => ['99999999999999999999', '99999999999999999999'],
        'array' => [['2'], 'array'],
    ]);

    it('throws the configuration exception for an int no string-backed case matches', function (): void {
        config()->set('toolbox.kt', 1);

        Config::enum('toolbox.kt', KeyType::class);
    })->throws(InvalidConfigurationException::class, 'must be one of [bigint, uuid, ulid], [1] given.');

    it('throws the nominated exception for a mismatched type', function (): void {
        Config::for(['priority' => 'x'], CustomConfigException::class)->enum('priority', Priority::class);
    })->throws(CustomConfigException::class, 'must be one of [1, 2, -1], [x] given.');
});
