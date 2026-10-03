<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('maps canonical values', function (): void {
    expect(KeyType::fromValue('bigint'))->toBe(KeyType::BigInt)
        ->and(KeyType::fromValue('uuid'))->toBe(KeyType::Uuid)
        ->and(KeyType::fromValue('ulid'))->toBe(KeyType::Ulid);
});

it('treats id as an unrecognized value, not a bigint synonym', function (): void {
    expect(KeyType::fromValue('id', KeyType::Ulid))->toBe(KeyType::Ulid);
});

it('is case- and whitespace-insensitive', function (): void {
    expect(KeyType::fromValue('  UUID '))->toBe(KeyType::Uuid);
});

it('silently falls back to the default for an unrecognized value', function (): void {
    expect(KeyType::fromValue('nonsense'))->toBe(KeyType::BigInt)
        ->and(KeyType::fromValue('nonsense', KeyType::Ulid))->toBe(KeyType::Ulid);
});

it('reads the configured key type, case-insensitive and trimmed', function (mixed $configured, KeyType $expected): void {
    config()->set('toolbox.key_type', $configured);

    expect(KeyType::fromConfig('toolbox.key_type', KeyType::BigInt))->toBe($expected);
})->with([
    ['bigint', KeyType::BigInt], ['uuid', KeyType::Uuid], ['ulid', KeyType::Ulid],
    ['  UUID ', KeyType::Uuid], ['Ulid', KeyType::Ulid], ["bigint\n", KeyType::BigInt],
]);

it('falls back to the default only when the key is absent or null', function (): void {
    expect(KeyType::fromConfig('toolbox.never_set'))->toBe(KeyType::BigInt)
        ->and(KeyType::fromConfig('toolbox.never_set', KeyType::Ulid))->toBe(KeyType::Ulid);

    config()->set('toolbox.key_type', null);

    expect(KeyType::fromConfig('toolbox.key_type'))->toBe(KeyType::BigInt)
        ->and(KeyType::fromConfig('toolbox.key_type', KeyType::Uuid))->toBe(KeyType::Uuid);
});

it('honours a KeyType case written straight into config', function (KeyType $type): void {
    // `'key_type' => KeyType::Uuid` in a host's config file must not silently
    // become bigint columns.
    config()->set('toolbox.key_type', $type);

    expect(KeyType::fromConfig('toolbox.key_type', KeyType::BigInt))->toBe($type);
})->with([KeyType::Uuid, KeyType::Ulid, KeyType::BigInt]);

it('THROWS on a present value that names no key type instead of reading the default', function (mixed $configured, string $given): void {
    config()->set('toolbox.key_type', $configured);

    expect(fn (): KeyType => KeyType::fromConfig('toolbox.key_type', KeyType::Uuid))->toThrow(
        InvalidConfigurationException::class,
        "Configuration value [toolbox.key_type] must be one of [bigint, uuid, ulid] (case-insensitive), [{$given}] given.",
    );
})->with([
    'a typo' => ['uiid', 'uiid'],
    'id is not a bigint synonym' => ['id', 'id'],
    'an empty string' => ['', "''"],
    'an integer' => [1, '1'],
    'false' => [false, 'false'],
    'an array' => [['uuid'], 'array'],
    'another enum' => [DatabaseDriver::Pgsql, DatabaseDriver::class],
]);
