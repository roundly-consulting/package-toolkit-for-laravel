<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

/**
 * The concrete schema-macro logic. Kept as plain static methods (rather than
 * inline macro closures that cross-call each other) so both the registered
 * Blueprint macros and their internal composition are statically analysable.
 */
final class BlueprintMacros
{
    /**
     * A single foreign-key column of the given key type, optionally nullable
     * and/or indexed.
     */
    public static function ownerKey(Blueprint $table, string $name, KeyType $type, bool $nullable, bool $index): ColumnDefinition
    {
        $column = match ($type) {
            KeyType::BigInt => $table->unsignedBigInteger($name),
            KeyType::Uuid => $table->uuid($name),
            KeyType::Ulid => $table->ulid($name),
        };

        if ($nullable) {
            $column->nullable();
        }

        if ($index) {
            $column->index();
        }

        return $column;
    }

    /**
     * A polymorphic `<name>_type` / `<name>_id` column pair (with composite
     * index) whose id column matches the given key type. `KeyType::BigInt`
     * always emits the numeric pair: Laravel's `morphs()` follows a host-wide
     * `Schema::morphUsingUuids()` / `morphUsingUlids()`, which would give a
     * bigint-keyed package a uuid/ulid id column it cannot write to.
     */
    public static function morphKey(Blueprint $table, string $name, KeyType $type, bool $nullable): void
    {
        match (true) {
            $type === KeyType::Uuid && $nullable => $table->nullableUuidMorphs($name),
            $type === KeyType::Uuid => $table->uuidMorphs($name),
            $type === KeyType::Ulid && $nullable => $table->nullableUlidMorphs($name),
            $type === KeyType::Ulid => $table->ulidMorphs($name),
            $nullable => $table->nullableNumericMorphs($name),
            default => $table->numericMorphs($name),
        };
    }

    /**
     * The `timestamps()` + `softDeletes()` audit convention.
     */
    public static function auditable(Blueprint $table): void
    {
        $table->timestamps();
        $table->softDeletes();
    }

    /**
     * A polymorphic subject column pair — the morph convention under a
     * domain-friendly name.
     */
    public static function polymorphicSubject(Blueprint $table, string $name, KeyType $type, bool $nullable): void
    {
        self::morphKey($table, $name, $type, $nullable);
    }
}
