<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures;

/**
 * A string-backed enum whose values look numeric, the shape a PHP config file
 * may hand an int for.
 */
enum Version: string
{
    case One = '1';

    case Two = '2';
}
