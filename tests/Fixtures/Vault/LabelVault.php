<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Vault;

/**
 * @see LabelManager
 */
final class LabelVault extends SharedVault
{
    protected static function getFacadeAccessor(): string
    {
        return LabelManager::class;
    }
}
