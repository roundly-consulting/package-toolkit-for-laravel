<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Exceptions;

use BackedEnum;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

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

    public static function notABoolean(string $key, mixed $value): self
    {
        $given = self::describe($value);

        return new self("Configuration value [{$key}] must be a boolean (true/false, 1/0, on/off or yes/no), [{$given}] given.");
    }

    public static function notAKeyType(string $key, mixed $value): self
    {
        $allowed = implode(', ', array_map(static fn (KeyType $type): string => $type->value, KeyType::cases()));
        $given = self::describe($value);

        return new self("Configuration value [{$key}] must be one of [{$allowed}] (case-insensitive), [{$given}] given.");
    }

    /**
     * @param  class-string  $contract
     */
    public static function notAnImplementation(string $key, string $contract, mixed $value): self
    {
        $given = self::describe($value);

        return new self("Configuration value [{$key}] must be a class-string of [{$contract}], [{$given}] given.");
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

    /**
     * How an offending value reads in a message: a string as written (`''` when
     * empty), a scalar as its PHP literal, anything else by its type.
     */
    private static function describe(mixed $value): string
    {
        return match (true) {
            $value === '' => "''",
            is_string($value) => $value,
            is_int($value), is_float($value), is_bool($value) => var_export($value, true),
            default => get_debug_type($value),
        };
    }
}
