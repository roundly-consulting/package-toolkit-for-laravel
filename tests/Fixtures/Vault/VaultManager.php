<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Vault;

use Illuminate\Support\Traits\Macroable;
use RuntimeException;
use SensitiveParameter;

/**
 * A facade root shaped like the fleet's managers: secrets marked `#[SensitiveParameter]` next
 * to harmless arguments, a sensitive variadic, a harmless variadic, and a method with no
 * secret at all. The methods that throw stand for an exception raised below the manager.
 */
class VaultManager
{
    use Macroable;

    public function check(#[SensitiveParameter] string $secret, string $label): bool
    {
        throw new RuntimeException("The [{$label}] check failed.");
    }

    public function verify(#[SensitiveParameter] string $secret, string $label): bool
    {
        return $secret === $label;
    }

    public function encode(#[SensitiveParameter] string $bytes): string
    {
        return bin2hex($bytes);
    }

    public function seal(string $label, #[SensitiveParameter] string ...$parts): string
    {
        throw new RuntimeException("Sealing [{$label}] failed.");
    }

    public function tag(#[SensitiveParameter] string $secret, string ...$labels): string
    {
        throw new RuntimeException('Tagging failed.');
    }

    public function describe(string $label): string
    {
        throw new RuntimeException("No [{$label}].");
    }
}
