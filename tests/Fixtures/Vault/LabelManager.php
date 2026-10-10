<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Vault;

use RuntimeException;
use SensitiveParameter;

/**
 * `check()` with its arguments the other way round from {@see VaultManager::check()}.
 */
final class LabelManager
{
    public function check(string $label, #[SensitiveParameter] string $secret): bool
    {
        throw new RuntimeException("The [{$label}] check failed.");
    }
}
