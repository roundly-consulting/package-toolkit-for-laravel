<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Vault;

use Illuminate\Support\Facades\Facade;

/**
 * The same root behind Laravel's stock facade: the control every redaction test is measured
 * against.
 *
 * @method static bool check(string $secret, string $label)
 * @method static string encode(string $bytes)
 *
 * @see VaultManager
 */
final class StockVault extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return VaultManager::class;
    }
}
