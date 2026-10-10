<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Vault;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\PackageToolkit\Concerns\RedactsSensitiveArguments;

/**
 * A facade someone left open, so a subclass can front a different root: the trait's lookup
 * must not hand the subclass this root's positions.
 *
 * @see VaultManager
 */
class SharedVault extends Facade
{
    use RedactsSensitiveArguments;

    protected static function getFacadeAccessor(): string
    {
        return VaultManager::class;
    }
}
