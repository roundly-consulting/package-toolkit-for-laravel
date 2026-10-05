<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox;

/**
 * An abstract-class contract for `bindFromConfig()`: a class, not an interface, so
 * `class_exists()` alone cannot tell it apart from an implementation.
 */
abstract class Salutation
{
    abstract public function salute(): string;
}
