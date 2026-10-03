<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Enums;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * The primary-key strategy a host application uses for the models a package
 * relates to. Because a package's schema freezes at its first tagged release,
 * this is configured up front rather than fixed to `bigint`, so UUID/ULID-keyed
 * models are supported alongside the auto-incrementing default.
 */
enum KeyType: string
{
    /** Auto-incrementing `unsignedBigInteger` keys (the Laravel default). */
    case BigInt = 'bigint';

    /** UUID string keys (models using `HasUuids`). */
    case Uuid = 'uuid';

    /** ULID string keys (models using `HasUlids`). */
    case Ulid = 'ulid';

    /**
     * Resolve the key type configured at `$key`: a `KeyType` case, or its value
     * (`bigint`, `uuid`, `ulid` — case-insensitive and trimmed). Falls back to
     * `$default` only when the key is not set (absent, null or blank — `''` or
     * whitespace); any other value THROWS, so
     * a typo such as `'uiid'` stops the app instead of silently building bigint
     * columns for a UUID-keyed host.
     *
     * @throws InvalidConfigurationException
     */
    public static function fromConfig(string $key, self $default = self::BigInt): self
    {
        $value = config($key);

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return $default;
        }

        if ($value instanceof self) {
            return $value;
        }

        return (is_string($value) ? self::parse($value) : null)
            ?? throw InvalidConfigurationException::notAKeyType($key, $value);
    }

    /**
     * Map a raw string to a key type, falling back to `$default` for anything
     * unrecognized. For a config value use {@see self::fromConfig()}, which
     * throws on an unrecognized one instead.
     */
    public static function fromValue(string $value, self $default = self::BigInt): self
    {
        return self::parse($value) ?? $default;
    }

    /**
     * The key type a raw string names (case-insensitive, trimmed), or null.
     */
    private static function parse(string $value): ?self
    {
        return match (strtolower(trim($value))) {
            'bigint' => self::BigInt,
            'uuid' => self::Uuid,
            'ulid' => self::Ulid,
            default => null,
        };
    }
}
