<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Exceptions;

use BackedEnum;

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

    public static function notABoolean(string $key): self
    {
        return new self("Configuration value [{$key}] must be a boolean (true/false, 1/0, on/off or yes/no).");
    }

    public static function notAnInteger(string $key): self
    {
        return new self("Configuration value [{$key}] must be an integer.");
    }

    public static function outOfRange(string $key, int $min, int $max): self
    {
        return new self("Configuration value [{$key}] must be between {$min} and {$max}.");
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     */
    public static function notAValidEnum(string $key, string $enum): self
    {
        $allowed = implode(', ', array_map(
            static fn (BackedEnum $case): string => (string) $case->value,
            $enum::cases(),
        ));

        return new self("Configuration value [{$key}] must be one of [{$allowed}].");
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
