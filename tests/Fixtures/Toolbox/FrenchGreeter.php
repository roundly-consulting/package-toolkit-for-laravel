<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox;

/**
 * A host-supplied implementation, to prove `bindFromConfig()` binds what config names.
 */
final class FrenchGreeter implements Greeter
{
    public function greet(): string
    {
        return 'bonjour';
    }
}
