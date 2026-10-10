<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Support;

use BackedEnum;
use Closure;
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
     * An integer. Falls back to `$default` only when not set (absent, null or
     * blank — `''` or whitespace); anything else
     * must be an `int` or a canonical integer string (an optional `-`, decimal
     * digits, surrounding whitespace ignored — every env value is a string), and
     * THROWS otherwise: `'five'`, `'5.5'`, `'5abc'`, `'1e3'`, `'0x10'`, `'+5'`,
     * an overflowing number, a float, a bool or an array never become a number.
     * `$min` / `$max`, when given, bound the result — the default included.
     */
    public function integer(string $key, int $default, ?int $min = null, ?int $max = null): int
    {
        $raw = $this->read($key);
        $value = $raw === null ? $default : self::toInteger($raw);

        if ($value === null) {
            throw $this->fail(InvalidConfigurationException::notAnInteger($key, $raw));
        }

        if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
            throw $this->fail(InvalidConfigurationException::outOfRange($key, $min, $max, $raw ?? $default));
        }

        return $value;
    }

    /**
     * A decimal number. Falls back to `$default` only when not set (absent, null
     * or blank — `''` or whitespace); anything else must be an `int`, a finite
     * `float`, or a canonical decimal string (an optional `-`, digits, an
     * optional `.` followed by digits, surrounding whitespace ignored), and
     * THROWS otherwise: `'abc'`, `'1e3'`, `'0,5'`, `'+1'`, `'.5'`, `'5.'`,
     * `'NAN'`, an overflowing number, `NAN`/`INF`, a bool or an array never
     * become a number. Canonical only, like {@see self::integer()}: `'.5'` and
     * `'5.'` are rejected rather than guessed at. `$min` / `$max`, when given,
     * bound the result inclusively — the default included, and a non-finite
     * default throws too.
     */
    public function float(string $key, float $default, ?float $min = null, ?float $max = null): float
    {
        $raw = $this->read($key);
        $value = $raw === null ? $default : self::toFloat($raw);

        if ($value === null || ! is_finite($value)) {
            throw $this->fail(InvalidConfigurationException::notAFloat($key, $raw ?? $default));
        }

        if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
            throw $this->fail(InvalidConfigurationException::outOfRange($key, $min, $max, $raw ?? $default));
        }

        return $value;
    }

    /**
     * A required string value; throws `missing` when not set (absent, null or
     * blank) and `notAString` for any other non-string.
     */
    public function requireString(string $key): string
    {
        $value = $this->read($key);

        if ($value === null) {
            throw $this->fail(InvalidConfigurationException::missing($key));
        }

        if (! is_string($value) || trim($value) === '') {
            throw $this->fail(InvalidConfigurationException::notAString($key, $value));
        }

        return $value;
    }

    /**
     * An optional string with a shipped default. Not set (absent, null or blank —
     * `''` or whitespace, a host's `KEY=`) returns `$default`; a present string
     * is returned AS GIVEN, never trimmed, so a secret or a pattern keeps every
     * character; any non-string (an int, a float, a bool, an array) THROWS
     * `notAString`. There is deliberately no switch that lets a blank value
     * through: blank means "use the default", as for every other reader.
     */
    public function string(string $key, string $default): string
    {
        $value = $this->read($key);

        if ($value === null) {
            return $default;
        }

        return is_string($value)
            ? $value
            : throw $this->fail(InvalidConfigurationException::notAString($key, $value));
    }

    /**
     * A list of strings, from a published config array (`['en', 'sk']`) or an env
     * comma list (`'en, sk'`). Items are trimmed, empty items are dropped and the
     * result is re-indexed; duplicates and inner spaces are kept. Not set (absent,
     * null or blank) — and a value that yields no items (`','`, `[]`, `[' ', '']`)
     * — returns `$default`, normalised the same way: blank means "use the
     * default", so a host cannot configure an empty list over a non-empty one.
     *
     * A value that is neither an array nor a string THROWS `notAList`; an array
     * item that is not a string THROWS `notAStringItem`. `$each`, when given,
     * sees every resulting item — the default's included, as {@see self::integer()}
     * bounds its default — and anything but a `true` return THROWS
     * `notAValidListItem`, naming the item.
     *
     * @param  array<string>  $default
     * @param  (Closure(string): bool)|null  $each
     * @return list<string>
     */
    public function list(string $key, array $default, ?Closure $each = null): array
    {
        $raw = $this->read($key);

        $items = match (true) {
            $raw === null => [],
            is_string($raw) => $this->toList($key, explode(',', $raw)),
            is_array($raw) => $this->toList($key, $raw),
            default => throw $this->fail(InvalidConfigurationException::notAList($key, $raw)),
        };

        if ($items === []) {
            $items = $this->toList($key, $default);
        }

        foreach ($items as $item) {
            if ($each !== null && $each($item) !== true) {
                throw $this->fail(InvalidConfigurationException::notAValidListItem($key, $item));
            }
        }

        return $items;
    }

    /**
     * A backed-enum value. Not set (absent, null or blank) returns `$default`, or
     * throws `missing` when no default is given. A case of the enum is returned as-is; any other value must
     * be one of the backing values — matched exactly and case-sensitively, after
     * coercion to the enum's backing type (see {@see self::toCase()}) — or it
     * THROWS, listing the allowed values. A default never stands in for a typo.
     *
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @param  TEnum|null  $default
     * @return TEnum
     */
    public function enum(string $key, string $enum, ?BackedEnum $default = null): BackedEnum
    {
        $value = $this->read($key);

        if ($value === null) {
            return $default ?? throw $this->fail(InvalidConfigurationException::missing($key));
        }

        return self::toCase($enum, $value)
            ?? throw $this->fail(InvalidConfigurationException::notAValidEnum($key, $enum, $value));
    }

    /**
     * A string from a fixed vocabulary, for a setting with no enum of its own.
     * Not set (absent, null or blank) returns `$default`; anything else must be one of `$allowed`
     * (exact, case-sensitive) or it THROWS, listing them — and so does a default
     * outside the vocabulary.
     *
     * @param  non-empty-list<string>  $allowed
     */
    public function oneOf(string $key, array $allowed, string $default): string
    {
        $value = $this->read($key) ?? $default;

        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw $this->fail(InvalidConfigurationException::notOneOf($key, $allowed, $value));
        }

        return $value;
    }

    /**
     * A boolean value: `true`/`1`/`on`/`yes` or `false`/`0`/`off`/`no`,
     * case-insensitive and trimmed. Absent, null or blank (`''`, whitespace — a
     * host's `KEY=`) is not set and returns `$default`; anything else THROWS, so a
     * typo such as `ALLOW_X=disabled` fails loudly instead of silently reading as
     * the default. The message names the key, the value and the accepted spellings.
     */
    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->read($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
            ?? throw $this->fail(InvalidConfigurationException::notABoolean($key, $value));
    }

    /**
     * The case of `$enum` a raw config value names, or null when it names none —
     * including a case of a different enum.
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
     * An int, or a canonical integer string (optional `-`, decimal digits,
     * optionally whitespace-padded) that fits in one; null for anything else.
     * Rejects what a blunt `(int)` cast would quietly turn into a different
     * number — `'--5'` (0), `'1.5'` (1), `'5abc'` (5), `'1e3'` (1000), or an
     * overflowing `'99999999999999999999'` (PHP_INT_MAX) — and an explicit `+`.
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

    /**
     * An int or a float as a float, or a canonical decimal string (optional `-`,
     * digits, optionally `.` and more digits, optionally whitespace-padded) as
     * the float it spells; null for anything else. A non-finite result — `NAN`,
     * `INF`, or a string too long for a float — is the caller's to reject.
     */
    private static function toFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value) || preg_match('/^\s*-?\d+(?:\.\d+)?\s*$/', $value) !== 1) {
            return null;
        }

        return (float) $value;
    }

    /**
     * The items of a config list, trimmed, with empty ones dropped and the keys
     * discarded; throws `notAStringItem` for the first item that is not a string.
     *
     * @param  array<mixed>  $items
     * @return list<string>
     */
    private function toList(string $key, array $items): array
    {
        $list = [];

        foreach ($items as $item) {
            if (! is_string($item)) {
                throw $this->fail(InvalidConfigurationException::notAStringItem($key, $item));
            }

            if (($item = trim($item)) !== '') {
                $list[] = $item;
            }
        }

        return $list;
    }

    /**
     * The raw value at `$key`, with a blank string (`''` or whitespace only — what a
     * host's `KEY=` line yields) read as null: not set, exactly like an absent key.
     */
    private function read(string $key): mixed
    {
        $value = $this->config === null ? config($key) : Arr::get($this->config, $key);

        return is_string($value) && trim($value) === '' ? null : $value;
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
