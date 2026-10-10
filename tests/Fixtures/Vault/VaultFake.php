<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Vault;

use RuntimeException;

/**
 * A fake as the fleet writes them: a subtype of the manager that records every call — and
 * whose overrides dropped the `#[SensitiveParameter]` attributes.
 */
final class VaultFake extends VaultManager
{
    /** @var list<list<string>> */
    public array $calls = [];

    public function check(string $secret, string $label): bool
    {
        $this->calls[] = ['check', $secret, $label];

        throw new RuntimeException('The fake refused the check.');
    }

    public function verify(string $secret, string $label): bool
    {
        $this->calls[] = ['verify', $secret, $label];

        return true;
    }
}
