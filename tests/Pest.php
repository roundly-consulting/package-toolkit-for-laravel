<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Testing\PHPStanTestCase;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Tests\TestCase;
use RoundlyConsulting\Testing\Database\DriverMatrix;

uses(TestCase::class)->in(__DIR__);

/**
 * The column type the **active driver** reports for a column emitted by the `ownerKey` /
 * `morphKey` macros. The macros are portable; the type name the engine reports back is
 * not, so a test that hard-codes one engine's vocabulary asserts the leg rather than the
 * macro.
 *
 * The mapping is worth reading, because it is the argument for the pgsql leg on a package
 * that ships no migrations:
 *
 * | KeyType | sqlite    | pgsql    |
 * |---------|-----------|----------|
 * | BigInt  | `integer` | `int8`   |
 * | Uuid    | `varchar` | `uuid`   |
 * | Ulid    | `varchar` | `bpchar` |
 *
 * SQLite collapses all three into two names: it reports `unsignedBigInteger()` and
 * `integer()` alike as `integer`, and cannot tell a uuid column from a ulid one — both
 * are `varchar`. So on sqlite alone these assertions could never prove `ownerKey` emitted
 * a 64-bit key rather than a 32-bit one, nor that the Uuid and Ulid branches differ at
 * all. Postgres gives each its own native type and makes the same assertions bite.
 */
function expectedKeyColumnType(KeyType $type): string
{
    return match (DriverMatrix::driver()) {
        'pgsql' => match ($type) {
            KeyType::BigInt => 'int8',
            KeyType::Uuid => 'uuid',
            KeyType::Ulid => 'bpchar',
        },
        default => match ($type) {
            KeyType::BigInt => 'integer',
            KeyType::Uuid, KeyType::Ulid => 'varchar',
        },
    };
}

/**
 * A real PHPStan class reflection, for asserting on the toolkit's PHPStan extension.
 *
 * @param  class-string  $class
 */
function classReflection(string $class): ClassReflection
{
    return PHPStanTestCase::createReflectionProvider()->getClass($class);
}

/**
 * Whether the given column participates in any index on the table.
 */
function hasColumnIndex(string $table, string $column): bool
{
    foreach (Schema::getIndexes($table) as $index) {
        if (in_array($column, $index['columns'], true)) {
            return true;
        }
    }

    return false;
}
