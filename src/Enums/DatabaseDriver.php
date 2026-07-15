<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Enums;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * The database drivers a package may special-case. Postgres is the common
 * odd-one-out (native `ilike`, functional indexes), so `isPgsql()` reads
 * cleaner than comparing magic strings at call sites.
 */
enum DatabaseDriver: string
{
    case Mariadb = 'mariadb';

    case Mysql = 'mysql';

    case Pgsql = 'pgsql';

    case Sqlite = 'sqlite';

    /**
     * The driver of the given connection (or the default connection when none
     * is supplied). **Throws for a driver the toolkit does not model** — and the
     * modelled set is deliberately closed while Laravel's is not (`sqlsrv` is a
     * first-party driver this enum does not carry).
     *
     * Use `current()` only on **boot / console / migration** paths that may fail
     * loudly. On a **request path**, never call it — an unmodelled driver would
     * turn a working endpoint into an uncaught 500. Instead branch with a
     * portable fallback:
     *
     * ```php
     * $isPgsql = DatabaseDriver::tryFrom($connection->getDriverName())?->isPgsql() ?? false;
     * ```
     *
     * `tryFrom()` returns null for any driver outside the four cases, so an
     * unmodelled engine degrades to the portable branch instead of throwing.
     */
    public static function current(?ConnectionInterface $connection = null): self
    {
        $connection ??= DB::connection();

        if (! $connection instanceof Connection) {
            throw InvalidConfigurationException::unsupportedDatabaseDriver($connection::class);
        }

        $driver = $connection->getDriverName();

        return self::tryFrom($driver)
            ?? throw InvalidConfigurationException::unsupportedDatabaseDriver($driver);
    }

    public function isPgsql(): bool
    {
        return $this === self::Pgsql;
    }
}
