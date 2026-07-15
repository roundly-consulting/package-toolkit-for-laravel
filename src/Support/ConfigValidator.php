<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Support;

use BackedEnum;
use Illuminate\Support\Arr;
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
     * out of range.
     */
    public function intBetween(string $key, int $min, int $max, int $default): int
    {
        $value = $this->read($key);

        if ($value === null) {
            $value = $default;
        }

        if (is_string($value) && $value !== '' && ctype_digit(ltrim($value, '-'))) {
            $value = (int) $value;
        }

        if (! is_int($value)) {
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
     * fails loudly instead of silently downgrading to a default.
     *
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum
     */
    public function enum(string $key, string $enum): BackedEnum
    {
        $value = $this->read($key);

        if ($value instanceof $enum) {
            return $value;
        }

        if ($value === null) {
            throw $this->fail(InvalidConfigurationException::missing($key));
        }

        if ((is_string($value) || is_int($value)) && ($case = $enum::tryFrom($value)) !== null) {
            return $case;
        }

        throw $this->fail(InvalidConfigurationException::notAValidEnum($key, $enum));
    }

    /**
     * A backed-enum value, falling back to `$default` for a missing or
     * unrecognized value (lenient by design — hence "Or"). Never use this for a
     * security parameter; reach for `enum()` instead.
     *
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @param  TEnum  $default
     * @return TEnum
     */
    public function enumOr(string $key, string $enum, BackedEnum $default): BackedEnum
    {
        $value = $this->read($key);

        if ($value instanceof $enum) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            return $enum::tryFrom($value) ?? $default;
        }

        return $default;
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
