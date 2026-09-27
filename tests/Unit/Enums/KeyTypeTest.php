<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Enums\KeyType;

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

it('reads the configured key type', function (): void {
    config()->set('toolbox.key_type', 'ulid');

    expect(KeyType::fromConfig('toolbox.key_type'))->toBe(KeyType::Ulid);
});

it('falls back when the config value is missing or not a string', function (): void {
    config()->set('toolbox.key_type', null);
    expect(KeyType::fromConfig('toolbox.key_type'))->toBe(KeyType::BigInt);

    config()->set('toolbox.key_type', ['array']);
    expect(KeyType::fromConfig('toolbox.key_type', KeyType::Uuid))->toBe(KeyType::Uuid);
});
