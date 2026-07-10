<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox;

/**
 * Target class for the config-gated facade alias declared by the fixture
 * provider.
 */
final class Toolbox
{
    public static function ping(): string
    {
        return 'pong';
    }
}
