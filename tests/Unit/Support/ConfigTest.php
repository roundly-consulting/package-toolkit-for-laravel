<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\CustomConfigException;

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
