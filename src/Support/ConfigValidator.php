<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Support;

use BackedEnum;
use Illuminate\Support\Arr;
use ReflectionEnum;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use Throwable;

/**
 * A validate-or-throw accessor bound to a specific source — an array a DTO was
 * handed, or the global config repository — and a specific exception class.
 *
 * This is what makes the toolkit's config validation safe to adopt inside a
 * `fromArray()` DTO: it validates the values it was HANDED, not whatever the
 * global repository happens to hold under the same key, and it throws the
 * package's OWN exception so a host's `catch (PasskeyException)` keeps catching
 * misconfiguration.
 */
final class ConfigValidator
{
    /**
     * @param  array<string, mixed>|null  $config  the array to validate, or null to read the global config repository
     * @param  class-string<Throwable>  $exception  thrown on any failure; must accept a string message
     */
    private function __construct(
        private readonly ?array $config,
        private readonly string $exception,
    ) {}

    /**
     * Validate the values inside an array the caller was handed, rather than
     * reading the global repository behind the caller's back.
     *
     * @param  array<string, mixed>  $config
     * @param  class-string<Throwable>  $exception
     */
    public static function forArray(array $config, string $exception = InvalidConfigurationException::class): self
    {
        return new self($config, $exception);
    }

    /**
     * Validate values read from the global config repository by key.
     *
     * @param  class-string<Throwable>  $exception
     */
    public static function forRepository(string $exception = InvalidConfigurationException::class): self
    {
        return new self(null, $exception);
    }

    /**
     * An integer value that must fall within `[$min, $max]`. Falls back to
     * `$default` when absent (null); throws when present but not an integer or
     * out of range. A (signed) integer string — every env value is a string —
     * is coerced; anything else, including a malformed `--5`, throws.
     */
    public function intBetween(string $key, int $min, int $max, int $default): int
    {
        $value = self::toInteger($this->read($key) ?? $default);

        if ($value === null) {
            throw $this->fail(InvalidConfigurationException::notAnInteger($key));
        }

        if ($value < $min || $value > $max) {
            throw $this->fail(InvalidConfigurationException::outOfRange($key, $min, $max));
        }

        return $value;
    }

    /**
     * A required, non-empty string value; throws when missing or the wrong type.
     */
    public function requireString(string $key): string
    {
        $value = $this->read($key);

        if ($value === null) {
            throw $this->fail(InvalidConfigurationException::missing($key));
        }

        if (! is_string($value) || trim($value) === '') {
            throw $this->fail(InvalidConfigurationException::notAString($key));
        }

        return $value;
    }

    /**
     * A strict backed-enum value: throws when missing or not a recognized case.
     * Use this — not `enumOr` — for security-sensitive parameters, so a typo
     * fails loudly instead of silently downgrading to a default. The value is
     * coerced to the enum's backing type first (see {@see self::toCase()}), so an
     * env string `'2'` resolves an int-backed case.
     *
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum
     */
    public function enum(string $key, string $enum): BackedEnum
    {
        $value = $this->read($key);

        if ($value === null) {
            throw $this->fail(InvalidConfigurationException::missing($key));
        }

        return self::toCase($enum, $value)
            ?? throw $this->fail(InvalidConfigurationException::notAValidEnum($key, $enum));
    }

    /**
     * A backed-enum value, falling back to `$default` for a missing or
     * unrecognized value — including one of a type the enum cannot be backed by
     * (lenient by design — hence "Or"). Never use this for a security parameter;
     * reach for `enum()` instead.
     *
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @param  TEnum  $default
     * @return TEnum
     */
    public function enumOr(string $key, string $enum, BackedEnum $default): BackedEnum
    {
        return self::toCase($enum, $this->read($key)) ?? $default;
    }

    /**
     * A boolean value coerced via `filter_var`, falling back to `$default` for a
     * missing or unparseable value.
     */
    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->read($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * A strict boolean value: falls back to `$default` only when absent (null),
     * and THROWS when present but unparseable. The vocabulary is exactly
     * {@see self::boolean()}'s (`filter_var`: true/1/on/yes, false/0/off/no/'',
     * case-insensitive) — only a typo now fails loudly instead of silently reading
     * as the default. Use this — not `boolean()` — for a security-relevant switch,
     * where `ALLOW_X=disabled` must never leave X on.
     */
    public function strictBoolean(string $key, bool $default = false): bool
    {
        $value = $this->read($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
            ?? throw $this->fail(InvalidConfigurationException::notABoolean($key));
    }

    /**
     * The case of `$enum` a raw config value names, or null when it names none.
     *
     * `tryFrom()` is typed on the enum's backing type, so under `strict_types` a
     * string handed to an int-backed enum is a `TypeError`, not a miss — and every
     * env value is a string. The value is therefore coerced to the backing type
     * first: an integer string for an int-backed enum, an int for a string-backed
     * one. Anything that does not coerce cleanly (a float, a bool, `'2.0'`,
     * `'--2'`, an array) names no case.
     *
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum|null
     */
    private static function toCase(string $enum, mixed $value): ?BackedEnum
    {
        if ($value instanceof $enum) {
            return $value;
        }

        $backing = (string) (new ReflectionEnum($enum))->getBackingType();

        $value = match (true) {
            $backing === 'int' => self::toInteger($value),
            is_string($value) => $value,
            is_int($value) => (string) $value,
            default => null,
        };

        return $value === null ? null : $enum::tryFrom($value);
    }

    /**
     * An int, or a (signed, optionally whitespace-padded) integer string that
     * fits in one; null for anything else. Rejects what a blunt `(int)` cast
     * would quietly turn into a different number — `'--5'` (0), `'1.5'` (1), or
     * an overflowing `'99999999999999999999'` (PHP_INT_MAX).
     */
    private static function toInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_numeric($value) || ! is_string($value) || preg_match('/^\s*-?\d+\s*$/', $value) !== 1) {
            return null;
        }

        // A numeric string adds up to an int — or to a float once it overflows one.
        $integer = $value + 0;

        return is_int($integer) ? $integer : null;
    }

    private function read(string $key): mixed
    {
        return $this->config === null ? config($key) : Arr::get($this->config, $key);
    }

    /**
     * Re-wrap the toolkit's canonical failure as the nominated exception class,
     * preserving the message so the package's own hierarchy is what a host
     * catches.
     */
    private function fail(InvalidConfigurationException $canonical): Throwable
    {
        if ($this->exception === InvalidConfigurationException::class) {
            return $canonical;
        }

        $class = $this->exception;

        return new $class($canonical->getMessage());
    }
}
