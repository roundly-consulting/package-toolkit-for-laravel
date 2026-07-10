<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Exceptions;

/**
 * Thrown when a package reads a configuration value that is missing, of the
 * wrong type, out of range, or otherwise unusable.
 */
final class InvalidConfigurationException extends PackageToolkitException
{
    public static function missing(string $key): self
    {
        return new self("Configuration value [{$key}] is required but missing.");
    }

    public static function notAString(string $key): self
    {
        return new self("Configuration value [{$key}] must be a non-empty string.");
    }

    public static function notAnInteger(string $key): self
    {
        return new self("Configuration value [{$key}] must be an integer.");
    }

    public static function outOfRange(string $key, int $min, int $max): self
    {
        return new self("Configuration value [{$key}] must be between {$min} and {$max}.");
    }

    public static function notAModel(string $key, mixed $value): self
    {
        $given = is_string($value) ? $value : get_debug_type($value);

        return new self("Configuration value [{$key}] must be a class-string of an Eloquent model, [{$given}] given.");
    }

    public static function unsupportedDatabaseDriver(string $driver): self
    {
        return new self("Unsupported database driver [{$driver}].");
    }
}
