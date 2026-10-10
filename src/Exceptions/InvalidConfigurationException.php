<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Exceptions;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

/**
 * Thrown when a package reads a configuration value that is missing, of the
 * wrong type, out of range, or otherwise unusable.
 *
 * Every "wrong value" message has one shape — `Configuration value [key] must
 * be …, [given] given.` — where `given` is the offending value as written (`''`
 * when empty), a scalar as its PHP literal, or anything else by its type.
 */
final class InvalidConfigurationException extends PackageToolkitException
{
    public static function missing(string $key): self
    {
        return new self("Configuration value [{$key}] is required but missing.");
    }

    public static function notAString(string $key, mixed $value): self
    {
        return self::mustBe($key, 'a non-empty string', $value);
    }

    public static function notABoolean(string $key, mixed $value): self
    {
        return self::mustBe($key, 'a boolean (true/false, 1/0, on/off or yes/no)', $value);
    }

    public static function notAKeyType(string $key, mixed $value): self
    {
        $allowed = array_map(static fn (KeyType $type): string => $type->value, KeyType::cases());

        return self::mustBe($key, 'one of ['.implode(', ', $allowed).'] (case-insensitive)', $value);
    }

    /**
     * @param  class-string  $contract
     */
    public static function notAnImplementation(string $key, string $contract, mixed $value): self
    {
        return self::mustBe($key, "a class-string of [{$contract}]", $value);
    }

    public static function notAnInteger(string $key, mixed $value): self
    {
        return self::mustBe($key, 'an integer', $value);
    }

    public static function notAFloat(string $key, mixed $value): self
    {
        return self::mustBe($key, 'a decimal number', $value);
    }

    public static function outOfRange(string $key, int|float|null $min, int|float|null $max, mixed $value): self
    {
        $bound = match (true) {
            $min !== null && $max !== null => "between {$min} and {$max}",
            $min !== null => "at least {$min}",
            default => "at most {$max}",
        };

        return self::mustBe($key, $bound, $value);
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     */
    public static function notAValidEnum(string $key, string $enum, mixed $value): self
    {
        $allowed = array_map(static fn (BackedEnum $case): string => (string) $case->value, $enum::cases());

        return self::notOneOf($key, $allowed, $value);
    }

    /**
     * @param  array<array-key, string>  $allowed
     */
    public static function notOneOf(string $key, array $allowed, mixed $value): self
    {
        return self::mustBe($key, 'one of ['.implode(', ', $allowed).']', $value);
    }

    /**
     * @param  class-string  $base
     */
    public static function notAModel(string $key, mixed $value, string $base = Model::class): self
    {
        return self::mustBe($key, "a class-string of [{$base}]", $value);
    }

    public static function unsupportedDatabaseDriver(string $driver): self
    {
        return new self("Unsupported database driver [{$driver}].");
    }

    private static function mustBe(string $key, string $expectation, mixed $value): self
    {
        return new self("Configuration value [{$key}] must be {$expectation}, [".self::describe($value).'] given.');
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
