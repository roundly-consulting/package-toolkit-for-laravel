<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Enums;

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
     * Resolve the key type from a config value, silently falling back to
     * `$default` for any unrecognized value (misconfiguration never throws —
     * the package keeps working on the safe bigint default). The legacy alias
     * `'id'` is accepted as a synonym for `'bigint'`.
     */
    public static function fromConfig(string $key, self $default = self::BigInt): self
    {
        $value = config($key);

        return is_string($value)
            ? self::fromValue($value, $default)
            : $default;
    }

    /**
     * Map a raw string to a key type, accepting the `'id'` bigint alias and
     * falling back to `$default` for anything unrecognized.
     */
    public static function fromValue(string $value, self $default = self::BigInt): self
    {
        return match (strtolower(trim($value))) {
            'bigint', 'id' => self::BigInt,
            'uuid' => self::Uuid,
            'ulid' => self::Ulid,
            default => $default,
        };
    }
}
