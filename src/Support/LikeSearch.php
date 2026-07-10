<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Support;

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
     * Postgres uses `ILIKE`; the other drivers a `LIKE`. The column identifier
     * is developer-supplied (grammar-wrapped); the needle and escape char are
     * bound — no user input ever reaches an identifier position.
     */
    public static function apply(Builder $query, string $column, string $term, string $boolean = 'and'): Builder
    {
        $operator = DatabaseDriver::current($query->getConnection())->isPgsql()
            ? 'ilike'
            : 'like';

        $wrapped = $query->getGrammar()->wrap($column);
        $needle = '%'.LikeEscaper::escape($term).'%';

        $expression = new RawExpression("{$wrapped} {$operator} ? escape ?");

        return $query->whereRaw($expression, [$needle, '\\'], $boolean);
    }
}
