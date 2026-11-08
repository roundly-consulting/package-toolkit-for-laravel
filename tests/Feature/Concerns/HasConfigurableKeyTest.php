<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Records\KeyedRecord;

/*
 * Runs on the configured test engine: on the pgsql leg the uuid key is a native `uuid` column,
 * so a malformed id reaching SQL would be an "invalid input syntax for type uuid" error, not a
 * miss. These tests prove the concern refuses it first.
 */

function createKeyedRecordsTable(KeyType $type): void
{
    config()->set('toolbox.primary_key_type', $type->value);

    Schema::create('keyed_records', function (Blueprint $table) use ($type): void {
        match ($type) {
            KeyType::BigInt => $table->id(),
            KeyType::Uuid => $table->uuid('id')->primary(),
            KeyType::Ulid => $table->ulid('id')->primary(),
        };

        $table->string('name');
    });
}

afterEach(function (): void {
    Schema::dropIfExists('keyed_records');
});

it('behaves as a plain auto-incrementing model on bigint', function (): void {
    createKeyedRecordsTable(KeyType::BigInt);

    $first = KeyedRecord::query()->create(['name' => 'first']);
    $second = KeyedRecord::query()->create(['name' => 'second']);

    expect($first->configuredKeyType())->toBe(KeyType::BigInt)
        ->and($first->getKeyType())->toBe('int')
        ->and($first->getIncrementing())->toBeTrue()
        ->and($first->usesUniqueIds())->toBeFalse()
        ->and($first->uniqueIds())->toBe([])
        ->and($first->getKey())->toBeInt()
        ->and($second->getKey())->toBe($first->getKey() + 1)
        ->and(KeyedRecord::query()->find($first->getKey())?->name)->toBe('first')
        ->and((new KeyedRecord)->resolveRouteBinding((string) $first->getKey())?->name)->toBe('first');
});

it('mints a time-ordered uuid7 key on uuid', function (): void {
    createKeyedRecordsTable(KeyType::Uuid);

    $record = KeyedRecord::query()->create(['name' => 'first']);

    expect($record->configuredKeyType())->toBe(KeyType::Uuid)
        ->and($record->getKeyType())->toBe('string')
        ->and($record->getIncrementing())->toBeFalse()
        ->and($record->uniqueIds())->toBe(['id'])
        ->and(Str::isUuid($record->getKey(), 7))->toBeTrue()
        ->and(KeyedRecord::query()->find($record->getKey())?->name)->toBe('first')
        ->and((new KeyedRecord)->resolveRouteBinding($record->getKey())?->name)->toBe('first');
});

it('mints a ulid key on ulid', function (): void {
    createKeyedRecordsTable(KeyType::Ulid);

    $record = KeyedRecord::query()->create(['name' => 'first']);

    expect($record->configuredKeyType())->toBe(KeyType::Ulid)
        ->and($record->getKeyType())->toBe('string')
        ->and($record->getIncrementing())->toBeFalse()
        ->and(Str::isUlid($record->getKey()))->toBeTrue()
        ->and(KeyedRecord::query()->find($record->getKey())?->name)->toBe('first')
        ->and((new KeyedRecord)->resolveRouteBinding($record->getKey())?->name)->toBe('first');
});

it('keeps an id that is set explicitly', function (KeyType $type): void {
    createKeyedRecordsTable($type);
    $id = $type === KeyType::Ulid ? (string) Str::ulid() : (string) Str::uuid();

    $record = KeyedRecord::query()->create(['id' => $id, 'name' => 'given']);

    expect($record->getKey())->toBe($id)
        ->and(KeyedRecord::query()->find($id)?->name)->toBe('given');
})->with([KeyType::Uuid, KeyType::Ulid]);

it('refuses a malformed route key before it reaches SQL', function (KeyType $type, string $id): void {
    createKeyedRecordsTable($type);
    KeyedRecord::query()->create(['name' => 'first']);

    expect(fn () => (new KeyedRecord)->resolveRouteBinding($id))->toThrow(ModelNotFoundException::class);
})->with([
    'garbage on uuid' => [KeyType::Uuid, 'not-a-key'],
    'a ulid on uuid' => [KeyType::Uuid, '01JD3V1T3QZ8YB8T6H1JX4N2QK'],
    'garbage on ulid' => [KeyType::Ulid, 'not-a-key'],
    'a uuid on ulid' => [KeyType::Ulid, '0195f0a4-1c2b-7d3e-8f40-123456789abc'],
]);

it('reads the configured key type case-insensitively', function (): void {
    config()->set('toolbox.primary_key_type', ' ULID ');

    expect((new KeyedRecord)->configuredKeyType())->toBe(KeyType::Ulid)
        ->and((new KeyedRecord)->getKeyType())->toBe('string');
});

it('defaults to bigint when the key type is not set', function (mixed $blank): void {
    config()->set('toolbox.primary_key_type', $blank);

    expect((new KeyedRecord)->configuredKeyType())->toBe(KeyType::BigInt)
        ->and((new KeyedRecord)->getIncrementing())->toBeTrue();
})->with(['null' => [null], 'blank' => ['']]);

it('THROWS on a key type typo instead of building the wrong key', function (): void {
    config()->set('toolbox.primary_key_type', 'uiid');

    expect(fn (): KeyedRecord => new KeyedRecord)->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [toolbox.primary_key_type] must be one of [bigint, uuid, ulid] (case-insensitive), [uiid] given.',
    );
});
