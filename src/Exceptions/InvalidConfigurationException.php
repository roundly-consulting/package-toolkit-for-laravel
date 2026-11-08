<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Exceptions;

use RoundlyConsulting\PackageToolkit\Exceptions\Concerns\DescribesConfigurationErrors;

/**
 * Thrown when a package reads a configuration value that is missing, of the
 * wrong type, out of range, or otherwise unusable.
 *
 * The factories — and the one message shape they share — live in
 * {@see DescribesConfigurationErrors}, so a package's own configuration exception
 * can use the same vocabulary without subclassing this one. This class stays
 * `final`: the trait is the extension point, not inheritance.
 */
final class InvalidConfigurationException extends PackageToolkitException
{
    use DescribesConfigurationErrors;

    public static function unsupportedDatabaseDriver(string $driver): self
    {
        return new self("Unsupported database driver [{$driver}].");
    }
}
