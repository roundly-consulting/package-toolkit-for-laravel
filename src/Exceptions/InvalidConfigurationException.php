<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Exceptions;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use SensitiveParameter;

/**
 * Thrown when a package reads a configuration value that is missing, of the
 * wrong type, out of range, or otherwise unusable.
 *
 * Every "wrong value" message has one shape — `Configuration value [key] must
 * be …, [given] given.` (`must contain …` for a list item) — where `given` is the
 * offending value (or list item) as written (`''` when empty), a scalar as its
 * PHP literal, or anything else by its type.
 *
 * Every value parameter is `#[SensitiveParameter]`: the message is the one place
 * a value shows, never the arguments of a stack frame, which error trackers and
 * trace loggers serialise.
 */
final class InvalidConfigurationException extends PackageToolkitException
{
    public static function missing(string $key): self
    {
        return new self("Configuration value [{$key}] is required but missing.");
    }

    public static function notAString(string $key, #[SensitiveParameter] mixed $value): self
    {
        return self::mustBe($key, 'a non-empty string', $value);
    }

    public static function notABoolean(string $key, #[SensitiveParameter] mixed $value): self
    {
        return self::mustBe($key, 'a boolean (true/false, 1/0, on/off or yes/no)', $value);
    }

    public static function notAKeyType(string $key, #[SensitiveParameter] mixed $value): self
    {
        $allowed = array_map(static fn (KeyType $type): string => $type->value, KeyType::cases());

        return self::mustBe($key, 'one of ['.implode(', ', $allowed).'] (case-insensitive)', $value);
    }

    /**
     * @param  class-string  $contract
     */
    public static function notAnImplementation(string $key, string $contract, #[SensitiveParameter] mixed $value): self
    {
        return self::mustBe($key, "a class-string of [{$contract}]", $value);
    }

    public static function notAnInteger(string $key, #[SensitiveParameter] mixed $value): self
    {
        return self::mustBe($key, 'an integer', $value);
    }

    public static function notAFloat(string $key, #[SensitiveParameter] mixed $value): self
    {
        return self::mustBe($key, 'a decimal number', $value);
    }

    public static function outOfRange(string $key, int|float|null $min, int|float|null $max, #[SensitiveParameter] mixed $value): self
    {
        $bound = match (true) {
            $min !== null && $max !== null => "between {$min} and {$max}",
            $min !== null => "at least {$min}",
            default => "at most {$max}",
        };

        return self::mustBe($key, $bound, $value);
    }

    public static function notAList(string $key, #[SensitiveParameter] mixed $value): self
    {
        return self::mustBe($key, 'a list of strings (an array or a comma-separated string)', $value);
    }

    public static function notAStringItem(string $key, #[SensitiveParameter] mixed $item): self
    {
        return self::must($key, 'contain only string items', $item);
    }

    public static function notAValidListItem(string $key, #[SensitiveParameter] string $item): self
    {
        return self::must($key, 'contain only valid items', $item);
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     */
    public static function notAValidEnum(string $key, string $enum, #[SensitiveParameter] mixed $value): self
    {
        $allowed = array_map(static fn (BackedEnum $case): string => (string) $case->value, $enum::cases());

        return self::notOneOf($key, $allowed, $value);
    }

    /**
     * @param  array<array-key, string>  $allowed
     */
    public static function notOneOf(string $key, array $allowed, #[SensitiveParameter] mixed $value): self
    {
        return self::mustBe($key, 'one of ['.implode(', ', $allowed).']', $value);
    }

    /**
     * @param  class-string  $base
     */
    public static function notAModel(string $key, #[SensitiveParameter] mixed $value, string $base = Model::class): self
    {
        return self::mustBe($key, "a class-string of [{$base}]", $value);
    }

    public static function unsupportedDatabaseDriver(string $driver): self
    {
        return new self("Unsupported database driver [{$driver}].");
    }

    private static function mustBe(string $key, string $expectation, #[SensitiveParameter] mixed $value): self
    {
        return self::must($key, "be {$expectation}", $value);
    }

    private static function must(string $key, string $requirement, #[SensitiveParameter] mixed $value): self
    {
        return new self("Configuration value [{$key}] must {$requirement}, [".self::describe($value).'] given.');
    }

    /**
     * How an offending value reads in a message: a string as written (`''` when
     * empty), a scalar as its PHP literal, anything else by its type.
     */
    private static function describe(#[SensitiveParameter] mixed $value): string
    {
        return match (true) {
            $value === '' => "''",
            is_string($value) => $value,
            is_int($value), is_float($value), is_bool($value) => var_export($value, true),
            default => get_debug_type($value),
        };
    }
}
