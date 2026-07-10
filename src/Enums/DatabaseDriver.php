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
     * is supplied). Throws for a driver the toolkit does not model.
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
