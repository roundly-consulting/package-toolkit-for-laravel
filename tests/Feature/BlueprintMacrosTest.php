<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

afterEach(function (): void {
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

    expect(Schema::getColumnType('ok_bigint', 'owner'))->toBe('integer');
});

it('emits a uuid owner key', function (): void {
    Schema::create('ok_uuid', function (Blueprint $table): void {
        $table->id();
        $table->ownerKey('owner', KeyType::Uuid);
    });

    expect(Schema::getColumnType('ok_uuid', 'owner'))->toBe('varchar');
});

it('emits a nullable ulid owner key without an index', function (): void {
    Schema::create('ok_ulid', function (Blueprint $table): void {
        $table->id();
        $table->ownerKey('owner', KeyType::Ulid, nullable: true, index: false);
    });

    expect(Schema::getColumnType('ok_ulid', 'owner'))->toBe('varchar')
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
        ->and(Schema::getColumnType('ok_morph', 'subject_id'))->toBe('varchar');
});

it('emits a plain bigint morph key', function (): void {
    Schema::create('ok_morph', function (Blueprint $table): void {
        $table->id();
        $table->morphKey('subject', KeyType::BigInt);
    });

    expect(Schema::hasColumn('ok_morph', 'subject_type'))->toBeTrue()
        ->and(Schema::getColumnType('ok_morph', 'subject_id'))->toBe('integer');
});

it('emits a nullable bigint morph key', function (): void {
    Schema::create('ok_morph', function (Blueprint $table): void {
        $table->id();
        $table->morphKey('subject', KeyType::BigInt, nullable: true);
    });

    expect(Schema::getColumnType('ok_morph', 'subject_id'))->toBe('integer');
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
        ->and(Schema::getColumnType('ok_subject', 'owner_id'))->toBe('varchar');
});
