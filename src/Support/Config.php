<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Support;

use BackedEnum;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * Validate-or-throw accessors for package configuration. These provide the
 * repeated *mechanism* (read a key, check its shape, fail loudly on
 * misconfiguration) — the domain bounds stay with each package's call site.
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
        $value = config($key);

        if ($value === null) {
            $value = $default;
        }

        if (is_string($value) && $value !== '' && ctype_digit(ltrim($value, '-'))) {
            $value = (int) $value;
        }

        if (! is_int($value)) {
            throw InvalidConfigurationException::notAnInteger($key);
        }

        if ($value < $min || $value > $max) {
            throw InvalidConfigurationException::outOfRange($key, $min, $max);
        }

        return $value;
    }

    /**
     * A required, non-empty string config value; throws when missing or of the
     * wrong type.
     */
    public static function requireString(string $key): string
    {
        $value = config($key);

        if ($value === null) {
            throw InvalidConfigurationException::missing($key);
        }

        if (! is_string($value) || trim($value) === '') {
            throw InvalidConfigurationException::notAString($key);
        }

        return $value;
    }

    /**
     * A backed-enum config value, falling back to `$default` for a missing or
     * unrecognized value (this accessor is lenient by design — hence "Or").
     *
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @param  TEnum  $default
     * @return TEnum
     */
    public static function enumOr(string $key, string $enum, BackedEnum $default): BackedEnum
    {
        $value = config($key);

        if ($value instanceof $enum) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            return $enum::tryFrom($value) ?? $default;
        }

        return $default;
    }

    /**
     * A boolean config value coerced via `filter_var`, falling back to
     * `$default` for a missing or unparseable value.
     */
    public static function boolean(string $key, bool $default = false): bool
    {
        $value = config($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
