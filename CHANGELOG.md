# Changelog

All notable changes to `package-toolkit-for-laravel` will be documented in this file.

## Unreleased

### Removed

- **`Support\LockedUpdate`** — removed. The helper turned relative writes
  (`usage = usage + 1`) into stale absolute writes, always opened its own
  transaction, always saved with timestamps, and used `firstOrFail()` — a
  lost-update hazard on money boundaries. It had no callers. Use
  `DB::transaction()` + `lockForUpdate()` (with a relative `increment()`/raw
  update) directly.

### Added

- **`Config::enum()`** — a strict backed-enum accessor that throws
  `InvalidConfigurationException` when the configured value is missing or
  unrecognized, so an env typo cannot silently downgrade a security parameter.
  `enumOr()` stays for genuinely optional values.
- **`Config::for($array, $exception)` / `Config::using($exception)`** and the
  underlying `Support\ConfigValidator` — validate the values inside an array a
  DTO was handed (a `fromArray()`) instead of reading the global repository by
  key, and nominate the exception class thrown on failure so a package's own
  hierarchy (`PasskeyException`, …) is preserved.

### Changed

- `Config`'s existing repository accessors (`intBetween`, `requireString`,
  `enumOr`, `boolean`) now delegate to `ConfigValidator` — behavior and messages
  are unchanged.
- `DatabaseDriver::current()` docblock now states the rule: use it on
  boot/console/migration paths; on request paths use `tryFrom()` with a portable
  fallback, since the enum is closed while Laravel's driver set is not.
