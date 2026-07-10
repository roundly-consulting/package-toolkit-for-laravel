<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

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
