<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Support\Str;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

/**
 * Drives a package model's own primary key from a `<pkg>.primary_key_type` config key, matching
 * the column the package's migrations emitted for the same value: auto-increment on `bigint`,
 * a time-ordered `uuid7` on `uuid`, a `ulid` on `ulid`.
 *
 * This is the **inbound** key — the package's own `id`, which other packages' polymorphic
 * columns point at. It is distinct from an **outbound** morph key (`<pkg>.key_type`, the type of
 * the host models the package points at).
 *
 * Laravel's {@see HasUniqueStringIds} keys `getKeyType()`, `getIncrementing()`, `uniqueIds()`
 * and route-binding validation off the single `$usesUniqueIds` flag, so flipping that flag in
 * the trait initializer is the whole seam: on `bigint` the model behaves exactly as if it had
 * never used the trait. On `uuid` / `ulid` a malformed route key is refused before it reaches
 * SQL, where Postgres would answer a native `uuid` column with an error instead of a miss.
 *
 * ```php
 * final class Ticket extends Model
 * {
 *     use HasConfigurableKey;
 *
 *     protected function primaryKeyTypeConfigKey(): string
 *     {
 *         return 'tickets.primary_key_type';
 *     }
 * }
 * ```
 *
 * The key type is read with {@see KeyType::fromConfig()}: not set is `bigint`, a typo throws.
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait HasConfigurableKey
{
    use HasUniqueStringIds;

    /**
     * The config key naming this model's primary-key type (`'tickets.primary_key_type'`).
     */
    abstract protected function primaryKeyTypeConfigKey(): string;

    /**
     * Turn Laravel's unique-string-id machinery off entirely on `bigint`, so `getKeyType()` /
     * `getIncrementing()` fall through to the auto-incrementing parent.
     */
    public function initializeHasUniqueStringIds(): void
    {
        $this->usesUniqueIds = $this->configuredKeyType() !== KeyType::BigInt;
    }

    /**
     * A time-ordered key of the configured type (`uuid7` or `ulid`), so id-ordered chunks keep
     * creation order. Never called on `bigint` — the database mints those.
     */
    public function newUniqueId(): string
    {
        return $this->configuredKeyType() === KeyType::Ulid
            ? (string) Str::ulid()
            : (string) Str::uuid7();
    }

    /**
     * The configured primary-key type of this model's table.
     */
    public function configuredKeyType(): KeyType
    {
        return KeyType::fromConfig($this->primaryKeyTypeConfigKey());
    }

    /**
     * Whether a route or lookup key has the configured shape: a ULID on `ulid`, a UUID (any
     * version, so ids a host set explicitly still resolve) otherwise.
     */
    protected function isValidUniqueId(mixed $value): bool
    {
        return $this->configuredKeyType() === KeyType::Ulid
            ? Str::isUlid($value)
            : Str::isUuid($value);
    }
}
