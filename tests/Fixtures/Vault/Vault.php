<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Vault;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\PackageToolkit\Concerns\RedactsSensitiveArguments;

/**
 * @method static bool check(string $secret, string $label)
 * @method static bool verify(string $secret, string $label)
 * @method static string encode(string $bytes)
 * @method static string seal(string $label, string ...$parts)
 * @method static string tag(string $secret, string ...$labels)
 * @method static string describe(string $label)
 *
 * @see VaultManager
 */
final class Vault extends Facade
{
    use RedactsSensitiveArguments;

    protected static function getFacadeAccessor(): string
    {
        return VaultManager::class;
    }
}
