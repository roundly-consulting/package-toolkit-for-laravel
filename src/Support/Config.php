<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Support;

use BackedEnum;
use Closure;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use SensitiveParameter;
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
     * An integer config value: `$default` only when the key is not set (absent,
     * null or blank);
     * otherwise an `int` or a canonical integer string (`'30'`, `'-5'`, `' 30 '`),
     * bounded by `$min` / `$max` when given. Anything else — `'five'`, `'5.5'`,
     * `'1e3'`, a bool — throws, and so does a value (or default) out of range.
     */
    public static function integer(string $key, #[SensitiveParameter] int $default, ?int $min = null, ?int $max = null): int
    {
        return ConfigValidator::forRepository()->integer($key, $default, $min, $max);
    }

    /**
     * A decimal config value: `$default` only when the key is not set (absent,
     * null or blank); otherwise an `int`, a finite `float` or a canonical decimal
     * string (`'0.25'`, `'-0.5'`, `' 1 '`, `'3'`), bounded inclusively by `$min` /
     * `$max` when given. Anything else — `'abc'`, `'1e3'`, `'0,5'`, `'+1'`,
     * `'.5'`, `'5.'`, `NAN`/`INF`, a bool — throws, and so does a value (or
     * default) out of range or a non-finite default.
     */
    public static function float(string $key, #[SensitiveParameter] float $default, ?float $min = null, ?float $max = null): float
    {
        return ConfigValidator::forRepository()->float($key, $default, $min, $max);
    }

    /**
     * A required string config value; throws `missing` when not set (absent,
     * null or blank) and when present but not a string.
     */
    public static function requireString(string $key): string
    {
        return ConfigValidator::forRepository()->requireString($key);
    }

    /**
     * An optional string config value with a shipped default: `$default` when the
     * key is not set (absent, null or blank); a present string is returned as
     * given, never trimmed; a non-string (int, float, bool, array) throws.
     */
    public static function string(string $key, #[SensitiveParameter] string $default): string
    {
        return ConfigValidator::forRepository()->string($key, $default);
    }

    /**
     * A list-of-strings config value, from a published array (`['en', 'sk']`) or
     * an env comma list (`'en, sk'`): items trimmed, empty ones dropped, result
     * re-indexed. `$default` (normalised the same way) when the key is not set
     * (absent, null or blank) or yields no items (`','`, `[]`). A non-array,
     * non-string value or a non-string item throws, and so does any item —
     * the default's included — that `$each` does not return `true` for.
     *
     * @param  array<string>  $default
     * @param  (Closure(string): bool)|null  $each
     * @return list<string>
     */
    public static function list(string $key, #[SensitiveParameter] array $default, ?Closure $each = null): array
    {
        return ConfigValidator::forRepository()->list($key, $default, $each);
    }

    /**
     * An optional secret (an API key, a signing secret, a password): null when the
     * key is not set (absent, null or blank); a present string is returned as
     * given, never trimmed; anything else throws, describing the value by its
     * type only (`[int] given.`) — never the value itself.
     */
    public static function secret(string $key): ?string
    {
        return ConfigValidator::forRepository()->secret($key);
    }

    /**
     * A required secret: as {@see self::secret()}, but a key that is not set
     * throws `missing`.
     */
    public static function requireSecret(string $key): string
    {
        return ConfigValidator::forRepository()->requireSecret($key);
    }

    /**
     * A list of secrets (a key ring), parsed like {@see self::list()} but with no
     * default — not set, or no items, is `[]` — and every failure describing the
     * value or item by its type only. Give `$each`'s parameter
     * `#[SensitiveParameter]` so a closure that throws cannot leak an item.
     *
     * @param  (Closure(string): bool)|null  $each
     * @return list<string>
     */
    public static function secretList(string $key, ?Closure $each = null): array
    {
        return ConfigValidator::forRepository()->secretList($key, $each);
    }

    /**
     * A backed-enum config value: `$default` only when the key is not set (absent,
     * null or blank)
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
    public static function enum(string $key, string $enum, #[SensitiveParameter] ?BackedEnum $default = null): BackedEnum
    {
        return ConfigValidator::forRepository()->enum($key, $enum, $default);
    }

    /**
     * A string config value from a fixed vocabulary (for a setting with no enum):
     * `$default` only when the key is not set (absent, null or blank); anything outside `$allowed`
     * (exact, case-sensitive) throws, listing it.
     *
     * @param  non-empty-list<string>  $allowed
     */
    public static function oneOf(string $key, array $allowed, #[SensitiveParameter] string $default): string
    {
        return ConfigValidator::forRepository()->oneOf($key, $allowed, $default);
    }

    /**
     * A boolean config value: `true`/`1`/`on`/`yes` or `false`/`0`/`off`/`no`
     * (case-insensitive). Not set — absent, null or blank (`''`, a host's `KEY=`)
     * — returns `$default`; anything else throws, so an env typo such as
     * `disabled` fails loudly instead of silently reading as the default.
     */
    public static function boolean(string $key, #[SensitiveParameter] bool $default = false): bool
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
    public static function for(#[SensitiveParameter] array $config, string $exception = InvalidConfigurationException::class): ConfigValidator
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
