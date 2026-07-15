<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('resolves the default connection driver', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::Sqlite);
});

it('resolves the driver of a given connection', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');

    expect(DatabaseDriver::current($connection))->toBe(DatabaseDriver::Pgsql);
});

it('reports whether the driver is postgres', function (): void {
    $pgsql = Mockery::mock(Connection::class);
    $pgsql->shouldReceive('getDriverName')->andReturn('pgsql');

    $mysql = Mockery::mock(Connection::class);
    $mysql->shouldReceive('getDriverName')->andReturn('mysql');

    expect(DatabaseDriver::current($pgsql)->isPgsql())->toBeTrue()
        ->and(DatabaseDriver::current($mysql)->isPgsql())->toBeFalse();
});

it('throws for a driver it does not model', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('sqlsrv');

    DatabaseDriver::current($connection);
})->throws(InvalidConfigurationException::class, 'Unsupported database driver [sqlsrv]');

it('tryFrom returns null for an unmodelled driver so request paths can degrade', function (): void {
    // The rule: request paths use tryFrom() + a portable fallback, never
    // current(), because the enum is closed while Laravel's driver set is not.
    expect(DatabaseDriver::tryFrom('sqlsrv'))->toBeNull()
        ->and(DatabaseDriver::tryFrom('sqlsrv')?->isPgsql() ?? false)->toBeFalse();
});

it('throws for a connection that cannot report its driver', function (): void {
    $connection = Mockery::mock(ConnectionInterface::class);

    DatabaseDriver::current($connection);
})->throws(InvalidConfigurationException::class);
