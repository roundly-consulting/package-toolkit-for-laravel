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
     * An integer config value that must fall within `[$min, $max]`. Falls back
     * to `$default` when the key is absent (null); throws when present but not
     * an integer or out of range.
     */
    public static function intBetween(string $key, int $min, int $max, int $default): int
    {
        return ConfigValidator::forRepository()->intBetween($key, $min, $max, $default);
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
     * A strict backed-enum config value: throws when the configured value is
     * missing or not a recognized case. Use this — not {@see self::enumOr()} —
     * for security-sensitive parameters, so an env typo fails loudly instead of
     * silently downgrading to a default.
     *
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum
     */
    public static function enum(string $key, string $enum): BackedEnum
    {
        return ConfigValidator::forRepository()->enum($key, $enum);
    }

    /**
     * A backed-enum config value, falling back to `$default` for a missing or
     * unrecognized value (this accessor is lenient by design — hence "Or"). For
     * a security parameter use {@see self::enum()} instead.
     *
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @param  TEnum  $default
     * @return TEnum
     */
    public static function enumOr(string $key, string $enum, BackedEnum $default): BackedEnum
    {
        return ConfigValidator::forRepository()->enumOr($key, $enum, $default);
    }

    /**
     * A boolean config value coerced via `filter_var`, falling back to
     * `$default` for a missing or unparseable value.
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
