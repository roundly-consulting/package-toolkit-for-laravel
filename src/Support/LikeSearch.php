<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;

/**
 * The concrete implementation behind the `whereLikeEscaped` query/eloquent
 * builder macros.
 */
final class LikeSearch
{
    /**
     * Add a case-insensitive, injection-safe "contains" match to the query.
     * User wildcards are escaped and matched with an explicit `ESCAPE '\'`
     * clause so `%`/`_` stay literal on every driver — SQLite has no default
     * escape character, so a plain `LIKE` would leave escaped wildcards live.
     * Postgres uses `ILIKE`; every other driver a `LIKE`. The column identifier
     * is developer-supplied (grammar-wrapped); the needle and escape char are
     * bound — no user input ever reaches an identifier position.
     *
     * This runs on request paths, so it must never throw for a driver the
     * {@see DatabaseDriver} enum does not model: an unmodelled driver (e.g.
     * `sqlsrv`) takes the portable `LIKE` branch.
     */
    public static function apply(Builder $query, string $column, string $term, string $boolean = 'and'): Builder
    {
        $connection = $query->getConnection();
        $driver = $connection instanceof Connection ? $connection->getDriverName() : '';

        $operator = (DatabaseDriver::tryFrom($driver)?->isPgsql() ?? false) ? 'ilike' : 'like';

        $needle = LikeEscaper::escape($term);

        // SQL Server also reads `[...]` as a character class; escaping the
        // opening bracket keeps it literal. Other engines treat `[` as plain text.
        if ($driver === 'sqlsrv') {
            $needle = str_replace('[', '\\[', $needle);
        }

        $wrapped = $query->getGrammar()->wrap($column);

        $expression = new RawExpression("{$wrapped} {$operator} ? escape ?");

        return $query->whereRaw($expression, ['%'.$needle.'%', '\\'], $boolean);
    }
}
