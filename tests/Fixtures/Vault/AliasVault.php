<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Vault;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\PackageToolkit\Concerns\RedactsSensitiveArguments;

/**
 * A facade over a container alias rather than a class-string: there is no type to read the
 * attributes from, so it forwards like a stock facade.
 */
final class AliasVault extends Facade
{
    use RedactsSensitiveArguments;

    protected static function getFacadeAccessor(): string
    {
        return 'toolbox.vault';
    }
}
