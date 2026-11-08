<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Exceptions\Concerns;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use SensitiveParameter;
use SensitiveParameterValue;

/**
 * The configuration-error vocabulary of the toolkit, as factories a package's own exception can
 * use: `use DescribesConfigurationErrors;` in an exception whose constructor takes the message
 * first (any {@see \Exception} subclass does), and `MyConfigException::outOfRange(...)` returns a
 * `MyConfigException` carrying exactly the message the toolkit's
 * {@see \RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException} would.
 *
 * Every "wrong value" message has one shape — `Configuration value [key] must be …, [given]
 * given.` (`must contain …` for a list item) — where `given` is the offending value (or list
 * item) as written (`''` when empty), a scalar as its PHP literal, or anything else by its type.
 * A value wrapped in {@see SensitiveParameterValue} is described by its type only
 * (`[int] given.`), which is how the secret readers keep a misconfigured secret out of the
 * message.
 *
 * Every value parameter is `#[SensitiveParameter]`: the message is the one place a value shows,
 * never the arguments of a stack frame, which error trackers and trace loggers serialise.
 *
 * The helpers are private and named so they cannot collide with a `describe()` or `must()` the
 * using class already has — a class method silently wins over a trait method of the same name.
 */
trait DescribesConfigurationErrors
{
    public static function missing(string $key): static
    {
        return new static("Configuration value [{$key}] is required but missing.");
    }

    public static function notAString(string $key, #[SensitiveParameter] mixed $value): static
    {
        return self::configurationValueMustBe($key, 'a non-empty string', $value);
    }

    public static function notABoolean(string $key, #[SensitiveParameter] mixed $value): static
    {
        return self::configurationValueMustBe($key, 'a boolean (true/false, 1/0, on/off or yes/no)', $value);
    }

    public static function notAKeyType(string $key, #[SensitiveParameter] mixed $value): static
    {
        $allowed = array_map(static fn (KeyType $type): string => $type->value, KeyType::cases());

        return self::configurationValueMustBe($key, 'one of ['.implode(', ', $allowed).'] (case-insensitive)', $value);
    }

    /**
     * @param  class-string  $contract
     */
    public static function notAnImplementation(string $key, string $contract, #[SensitiveParameter] mixed $value): static
    {
        return self::configurationValueMustBe($key, "a class-string of [{$contract}]", $value);
    }

    public static function notAnInteger(string $key, #[SensitiveParameter] mixed $value): static
    {
        return self::configurationValueMustBe($key, 'an integer', $value);
    }

    /**
     * An integer setting that can also be switched off: neither an integer nor one of the
     * off words.
     *
     * @param  array<array-key, string>  $offWords
     */
    public static function notAnIntegerOrOff(string $key, array $offWords, #[SensitiveParameter] mixed $value): static
    {
        return self::configurationValueMustBe($key, 'an integer or one of ['.implode(', ', $offWords).']', $value);
    }

    public static function notAFloat(string $key, #[SensitiveParameter] mixed $value): static
    {
        return self::configurationValueMustBe($key, 'a decimal number', $value);
    }

    public static function outOfRange(string $key, int|float|null $min, int|float|null $max, #[SensitiveParameter] mixed $value): static
    {
        return self::configurationValueMustBe($key, self::configurationBound($min, $max), $value);
    }

    public static function notAList(string $key, #[SensitiveParameter] mixed $value): static
    {
        return self::configurationValueMustBe($key, 'a list of strings (an array or a comma-separated string)', $value);
    }

    public static function notAStringItem(string $key, #[SensitiveParameter] mixed $item): static
    {
        return self::configurationValueMust($key, 'contain only string items', $item);
    }

    public static function notAValidListItem(string $key, #[SensitiveParameter] string|SensitiveParameterValue $item): static
    {
        return self::configurationValueMust($key, 'contain only valid items', $item);
    }

    public static function notAnIntegerList(string $key, #[SensitiveParameter] mixed $value): static
    {
        return self::configurationValueMustBe($key, 'a list of integers (an array or a comma-separated string)', $value);
    }

    public static function notAnIntegerItem(string $key, #[SensitiveParameter] mixed $item): static
    {
        return self::configurationValueMust($key, 'contain only integer items', $item);
    }

    public static function itemOutOfRange(string $key, int|float|null $min, int|float|null $max, #[SensitiveParameter] mixed $item): static
    {
        return self::configurationValueMust($key, 'contain only items '.self::configurationBound($min, $max), $item);
    }

    /**
     * A map of names to strings that is not one: not an array, or an entry keyed by something
     * other than a non-empty string name (the key is what `$value` then describes). A bad value
     * under a good name is `notAString("{$key}.{$name}", …)`, which names the entry to fix.
     */
    public static function notAStringMap(string $key, #[SensitiveParameter] mixed $value): static
    {
        return self::configurationValueMustBe($key, 'a map of names to non-empty strings', $value);
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     */
    public static function notAValidEnum(string $key, string $enum, #[SensitiveParameter] mixed $value): static
    {
        $allowed = array_map(static fn (BackedEnum $case): string => (string) $case->value, $enum::cases());

        return self::notOneOf($key, $allowed, $value);
    }

    /**
     * @param  array<array-key, string>  $allowed
     */
    public static function notOneOf(string $key, array $allowed, #[SensitiveParameter] mixed $value): static
    {
        return self::configurationValueMustBe($key, 'one of ['.implode(', ', $allowed).']', $value);
    }

    /**
     * @param  class-string  $base
     */
    public static function notAModel(string $key, #[SensitiveParameter] mixed $value, string $base = Model::class): static
    {
        return self::configurationValueMustBe($key, "a class-string of [{$base}]", $value);
    }

    private static function configurationBound(int|float|null $min, int|float|null $max): string
    {
        return match (true) {
            $min !== null && $max !== null => "between {$min} and {$max}",
            $min !== null => "at least {$min}",
            default => "at most {$max}",
        };
    }

    private static function configurationValueMustBe(string $key, string $expectation, #[SensitiveParameter] mixed $value): static
    {
        return self::configurationValueMust($key, "be {$expectation}", $value);
    }

    private static function configurationValueMust(string $key, string $requirement, #[SensitiveParameter] mixed $value): static
    {
        return new static("Configuration value [{$key}] must {$requirement}, [".self::describeConfigurationValue($value).'] given.');
    }

    /**
     * How an offending value reads in a message: a string as written (`''` when empty), a
     * scalar as its PHP literal, anything else by its type — and a value wrapped in
     * {@see SensitiveParameterValue} by the type of what it wraps, never its content.
     */
    private static function describeConfigurationValue(#[SensitiveParameter] mixed $value): string
    {
        return match (true) {
            $value instanceof SensitiveParameterValue => get_debug_type($value->getValue()),
            $value === '' => "''",
            is_string($value) => $value,
            is_int($value), is_float($value), is_bool($value) => var_export($value, true),
            default => get_debug_type($value),
        };
    }
}
