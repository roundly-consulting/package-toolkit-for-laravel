<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures;

/**
 * An int-backed enum, the shape an env-driven config value (always a string)
 * has to be coerced into.
 */
enum Priority: int
{
    case Low = 1;

    case High = 2;

    case Below = -1;
}
