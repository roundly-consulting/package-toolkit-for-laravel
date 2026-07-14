<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Testing\PHPStanTestCase;
use RoundlyConsulting\PackageToolkit\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

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
