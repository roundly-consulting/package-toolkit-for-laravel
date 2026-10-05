<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

afterEach(function (): void {
    // A host-wide morph default leaking out of one test would retype every later morph column.
    Schema::defaultMorphKeyType('int');

    foreach (['ok_bigint', 'ok_uuid', 'ok_ulid', 'ok_morph', 'ok_audit', 'ok_subject'] as $table) {
        Schema::dropIfExists($table);
    }
});

it('registers the blueprint macros through the provider boot', function (): void {
    expect(Blueprint::hasMacro('ownerKey'))->toBeTrue()
        ->and(Blueprint::hasMacro('morphKey'))->toBeTrue()
        ->and(Blueprint::hasMacro('auditable'))->toBeTrue()
        ->and(Blueprint::hasMacro('polymorphicSubject'))->toBeTrue();
});

it('emits a bigint owner key with index', function (): void {
    Schema::create('ok_bigint', function (Blueprint $table): void {
        $table->id();
        $table->ownerKey('owner', KeyType::BigInt);
    });

    expect(Schema::getColumnType('ok_bigint', 'owner'))->toBe(expectedKeyColumnType(KeyType::BigInt));
});

it('emits a uuid owner key', function (): void {
    Schema::create('ok_uuid', function (Blueprint $table): void {
        $table->id();
        $table->ownerKey('owner', KeyType::Uuid);
    });

    expect(Schema::getColumnType('ok_uuid', 'owner'))->toBe(expectedKeyColumnType(KeyType::Uuid));
});

it('emits a nullable ulid owner key without an index', function (): void {
    Schema::create('ok_ulid', function (Blueprint $table): void {
        $table->id();
        $table->ownerKey('owner', KeyType::Ulid, nullable: true, index: false);
    });

    expect(Schema::getColumnType('ok_ulid', 'owner'))->toBe(expectedKeyColumnType(KeyType::Ulid))
        ->and(hasColumnIndex('ok_ulid', 'owner'))->toBeFalse();
});

it('indexes the owner key by default', function (): void {
    Schema::create('ok_bigint', function (Blueprint $table): void {
        $table->id();
        $table->ownerKey('owner', KeyType::BigInt);
    });

    expect(hasColumnIndex('ok_bigint', 'owner'))->toBeTrue();
});

it('emits a morph key pair of the right id type', function (): void {
    Schema::create('ok_morph', function (Blueprint $table): void {
        $table->id();
        $table->morphKey('subject', KeyType::Uuid);
    });

    expect(Schema::hasColumn('ok_morph', 'subject_type'))->toBeTrue()
        ->and(Schema::hasColumn('ok_morph', 'subject_id'))->toBeTrue()
        ->and(Schema::getColumnType('ok_morph', 'subject_id'))->toBe(expectedKeyColumnType(KeyType::Uuid));
});

it('emits a plain bigint morph key', function (): void {
    Schema::create('ok_morph', function (Blueprint $table): void {
        $table->id();
        $table->morphKey('subject', KeyType::BigInt);
    });

    expect(Schema::hasColumn('ok_morph', 'subject_type'))->toBeTrue()
        ->and(Schema::getColumnType('ok_morph', 'subject_id'))->toBe(expectedKeyColumnType(KeyType::BigInt));
});

it('emits a nullable bigint morph key', function (): void {
    Schema::create('ok_morph', function (Blueprint $table): void {
        $table->id();
        $table->morphKey('subject', KeyType::BigInt, nullable: true);
    });

    expect(Schema::getColumnType('ok_morph', 'subject_id'))->toBe(expectedKeyColumnType(KeyType::BigInt));
});

it('adds the auditable timestamp and soft-delete columns', function (): void {
    Schema::create('ok_audit', function (Blueprint $table): void {
        $table->id();
        $table->auditable();
    });

    expect(Schema::hasColumn('ok_audit', 'created_at'))->toBeTrue()
        ->and(Schema::hasColumn('ok_audit', 'updated_at'))->toBeTrue()
        ->and(Schema::hasColumn('ok_audit', 'deleted_at'))->toBeTrue();
});

it('adds a polymorphic subject column pair', function (): void {
    Schema::create('ok_subject', function (Blueprint $table): void {
        $table->id();
        $table->polymorphicSubject('owner', KeyType::Ulid, nullable: true);
    });

    expect(Schema::hasColumn('ok_subject', 'owner_type'))->toBeTrue()
        ->and(Schema::getColumnType('ok_subject', 'owner_id'))->toBe(expectedKeyColumnType(KeyType::Ulid));
});

it('keeps a bigint morph key numeric after the host switches its morph default', function (string $hostDefault, bool $nullable): void {
    match ($hostDefault) {
        'uuid' => Schema::morphUsingUuids(),
        'ulid' => Schema::morphUsingUlids(),
    };

    try {
        Schema::create('ok_morph', function (Blueprint $table) use ($nullable): void {
            $table->id();
            $table->morphKey('subject', KeyType::BigInt, nullable: $nullable);
            $table->polymorphicSubject('target', KeyType::BigInt, nullable: $nullable);
        });
    } finally {
        Schema::defaultMorphKeyType('int');
    }

    // The package's key type decides the id column, not the host's `morphUsingUuids()`: a
    // uuid `subject_id` would refuse every integer key the package writes into it.
    expect(Schema::getColumnType('ok_morph', 'subject_id'))->toBe(expectedKeyColumnType(KeyType::BigInt))
        ->and(Schema::getColumnType('ok_morph', 'target_id'))->toBe(expectedKeyColumnType(KeyType::BigInt));
})->with([
    'Schema::morphUsingUuids()' => ['uuid'],
    'Schema::morphUsingUlids()' => ['ulid'],
])->with([
    'required' => [false],
    'nullable' => [true],
]);

/**
 * The DDL a `Schema::create()` of a one-column-pair table compiles to on the active
 * connection, without running it.
 *
 * @return list<string>
 */
function compiledMorphTable(Closure $columns): array
{
    $blueprint = new Blueprint(Schema::getConnection(), 'ok_morph', $columns);
    $blueprint->create();

    return $blueprint->toSql();
}

it('compiles a bigint morph key to exactly the DDL of Laravel\'s default morphs', function (bool $nullable, string $macro): void {
    // The macro used to call `morphs()` / `nullableMorphs()`. Under Laravel's default morph
    // key type those are the numeric pair, so the 30 consumer migrations built on this
    // macro must compile to the same statements, byte for byte, after the fix.
    expect(Builder::$defaultMorphKeyType)->toBe('int');

    $package = compiledMorphTable(fn (Blueprint $table) => $table->{$macro}('subject', KeyType::BigInt, nullable: $nullable));
    $laravel = compiledMorphTable(fn (Blueprint $table) => $nullable ? $table->nullableMorphs('subject') : $table->morphs('subject'));

    expect($package)->not->toBeEmpty()->toBe($laravel);
})->with([
    'required' => [false],
    'nullable' => [true],
])->with(['morphKey', 'polymorphicSubject']);
