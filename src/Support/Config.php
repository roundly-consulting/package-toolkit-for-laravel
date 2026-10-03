<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Support;

use BackedEnum;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use Throwable;

/**
 * Validate-or-throw accessors for package configuration. These provide the
 * repeated *mechanism* (read a key, check its shape, fail loudly on
 * misconfiguration) — the domain bounds stay with each package's call site.
 *
 * The static helpers read the **global config repository** by key and throw the
 * toolkit's own {@see InvalidConfigurationException}. When you instead need to
 * validate an array you were handed (a `fromArray()` DTO), or to throw your
 * package's own exception, start a {@see ConfigValidator} via {@see self::for()}
 * or {@see self::using()}.
 */
final class Config
{
    /**
     * An integer config value: `$default` only when the key is absent (null);
     * otherwise an `int` or a canonical integer string (`'30'`, `'-5'`, `' 30 '`),
     * bounded by `$min` / `$max` when given. Anything else — `'five'`, `'5.5'`,
     * `'1e3'`, `''`, a bool — throws, and so does a value (or default) out of range.
     */
    public static function integer(string $key, int $default, ?int $min = null, ?int $max = null): int
    {
        return ConfigValidator::forRepository()->integer($key, $default, $min, $max);
    }

    /**
     * A required, non-empty string config value; throws when missing or of the
     * wrong type.
     */
    public static function requireString(string $key): string
    {
        return ConfigValidator::forRepository()->requireString($key);
    }

    /**
     * A backed-enum config value: `$default` only when the key is absent (null)
     * — or a throw when no default is given. A case, or its exact
     * (case-sensitive) backing value, resolves; anything else throws, listing the
     * allowed values, so an env typo never silently downgrades to the default.
     *
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @param  TEnum|null  $default
     * @return TEnum
     */
    public static function enum(string $key, string $enum, ?BackedEnum $default = null): BackedEnum
    {
        return ConfigValidator::forRepository()->enum($key, $enum, $default);
    }

    /**
     * A string config value from a fixed vocabulary (for a setting with no enum):
     * `$default` only when the key is absent (null); anything outside `$allowed`
     * (exact, case-sensitive) throws, listing it.
     *
     * @param  non-empty-list<string>  $allowed
     */
    public static function oneOf(string $key, array $allowed, string $default): string
    {
        return ConfigValidator::forRepository()->oneOf($key, $allowed, $default);
    }

    /**
     * A boolean config value: falls back to `$default` only when the key is
     * absent (null), and throws when present but unparseable. The vocabulary is
     * `filter_var`'s: `true`/`1`/`on`/`yes` and `false`/`0`/`off`/`no`/`''`,
     * case-insensitive, so an env typo such as `disabled` fails loudly instead
     * of silently reading as the default.
     */
    public static function boolean(string $key, bool $default = false): bool
    {
        return ConfigValidator::forRepository()->boolean($key, $default);
    }

    /**
     * Validate the values inside an array you were handed (e.g. the payload a
     * DTO's `fromArray()` received), rather than reading the global repository
     * behind the caller's back. Optionally nominate the exception class thrown
     * on failure so your package's own hierarchy is preserved.
     *
     * @param  array<string, mixed>  $config
     * @param  class-string<Throwable>  $exception
     */
    public static function for(array $config, string $exception = InvalidConfigurationException::class): ConfigValidator
    {
        return ConfigValidator::forArray($config, $exception);
    }

    /**
     * Validate values read from the global config repository, but throw your
     * package's own exception class instead of the toolkit's.
     *
     * @param  class-string<Throwable>  $exception
     */
    public static function using(string $exception): ConfigValidator
    {
        return ConfigValidator::forRepository($exception);
    }
}
