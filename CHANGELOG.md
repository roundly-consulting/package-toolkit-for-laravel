# Changelog

All notable changes to `package-toolkit-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

### Added

- Five config readers on `Config` and `ConfigValidator`, with the same "not set means the
  default, anything else is validated or throws" contract as the others:
  - `optionalString($key)`: a string, or `null` when not set.
  - `integerList($key, $default, $min, $max)`: a list of integers from an array or an env
    comma list (`'1, 2, 3'`), with every item bounded, the default's included.
  - `optionalInteger($key, $default, $min, $max, offWords: ['off'])`: an integer that a host
    can switch off. An off word (case-insensitive) reads as `null`.
  - `implementation($key, $contract, $default)`: the class-string of an instantiable
    implementation of a contract. Anything else throws `notAnImplementation`.
  - `stringMap($key)`: a map of names to non-empty strings. A bad value names its entry
    (`[key.name]`).
- `Exceptions\Concerns\DescribesConfigurationErrors`: the configuration-error factories
  (`missing()`, `notAString()`, `outOfRange()`, `notAnImplementation()`, …) as a trait that
  returns `static`. A package's own configuration exception can use it to throw the toolkit's
  messages as its own type. `InvalidConfigurationException` uses it and stays `final`, and its
  messages are unchanged. New factories for the new readers: `notAnIntegerList()`,
  `notAnIntegerItem()`, `itemOutOfRange()`, `notAnIntegerOrOff()` and `notAStringMap()`.

## 2.0.0 - 2026-11-07

### Changed

- **Breaking:** requires Laravel 13; Laravel 12 is no longer supported. Upgrade the application to Laravel 13 before updating.
- Requires the 2.x releases of the Roundly packages it builds on (their Laravel 13 releases).
- Maintenance: tested on PHP 8.4, 8.5 and 8.6; the test suite runs on Pest 5.

## 1.4.0 - 2026-10-20

### Added

- `Package::hasRoutes()` takes `enabledByDefault` (default `true`, so nothing changes for
  existing packages). Pass `false` for an opt-in API: a switch that is not set — the key missing
  from a published config that overrides only part of its array (the merge is shallow), `null`,
  or a bare `env()` with the variable unset — then keeps the routes off instead of loading them.
  `RouteDeclaration` carries the flag.

### Changed

- Maintenance: requires the latest roundly packages — dev: testing `^1.2.1`.
- Maintenance: the real-engine CI leg starts Postgres on tmpfs with durability off, so it
  boots while dependencies install and the leg runs faster.

## 1.3.0 - 2026-10-10

### Added

- `Config::secret()`, `Config::requireSecret()` and `Config::secretList()` (and the same three
  on `ConfigValidator`) read API keys, signing secrets and key rings. They parse like
  `string()`, `requireString()` and `list()` (`secret()` returns `null` and `secretList()` `[]`
  when the key is not set), but a misconfigured value or list item is described by its type
  only (`[int] given.`). The value never shows in the message, the trace or a chained
  exception. Every `InvalidConfigurationException` factory describes a value wrapped in
  `SensitiveParameterValue` the same way.
- `Concerns\RedactsSensitiveArguments`, a facade trait. Add `use RedactsSensitiveArguments;`
  to a facade and its own stack frame hides exactly the arguments the root method marks
  `#[SensitiveParameter]`: positional, named and variadic, also when the root fails to
  resolve. Harmless arguments stay visible. A stock facade's `__callStatic` frame carries every
  argument raw, so a secret passed through it reached error trackers that collect frame
  arguments. Return values, scalar coercion, `swap()` fakes and `shouldReceive()` behave
  exactly as with Laravel's own facade.

### Security

- A configuration value no longer shows in the arguments of an exception's stack frames
  (`getTrace()`, `getTraceAsString()`) when `zend.exception_ignore_args` is off. Every
  `InvalidConfigurationException` factory's value parameter, the readers' `$default`s and the
  array handed to `Config::for()` / `ConfigValidator::forArray()` are `#[SensitiveParameter]`.
  Error trackers that collect frame arguments, `print_r($e)` and trace loggers used to get the
  value. The messages are unchanged.

## 1.2.0 - 2026-10-10

### Added

- `Config::float()` (and `ConfigValidator::float()`) reads a decimal setting such as a sample
  rate: an int, a float or a canonical decimal string (`'0.25'`, `'-0.5'`), bounded inclusively
  by optional `$min` / `$max`. A blank value reads as the default; `'abc'`, `'1e3'`, `'0,5'`,
  `'.5'`, `NAN`/`INF` and out-of-range values or defaults throw.
- `Config::string()` (and `ConfigValidator::string()`) reads an optional string with a shipped
  default. A blank `KEY=` now means the default instead of `''`; a present string comes back
  untrimmed, and a non-string (int, bool, array) throws.
- `Config::list()` (and `ConfigValidator::list()`) reads a list of strings from a published
  config array or an env comma list (`'en, sk'`): items trimmed, empty ones dropped. A blank
  value or one with no items reads as the default. An optional `$each` closure validates every
  item, the default's included, and a rejected item throws with the item named.

## 1.1.0 - 2026-10-05

### Added

- `MigrationPublisher::nextTimestamp()` hands out publish timestamps from one process-wide
  cursor, the same one the base provider uses, so a custom publish command's migrations order
  after everything else published in the same run. `MigrationPublisher::resetTimestamps()`
  restarts it for test suites that pin publish timestamps.

### Changed

- A published route file now replaces the package's: once a host has run
  `vendor:publish --tag=<pkg>-routes`, the published `routes/<file>` loads instead of the
  package's file, so edits to it take effect. If you `require` that copy from
  `routes/web.php` (or another route file), remove the line, or its routes register twice.
- Maintenance: `composer.json` `homepage` and `support.docs` point at the package's
  documentation page.
- Documentation: the README hero image uses an absolute URL, so it renders on Packagist and
  other sites.

### Fixed

- `morphKey()` and `polymorphicSubject()` with `KeyType::BigInt` always build a numeric `*_id`
  column. A host that called `Schema::morphUsingUuids()` / `morphUsingUlids()` used to get a
  uuid/ulid column that refused the package's integer keys. Without those calls the generated
  schema is unchanged.
- `whereLikeEscaped()` on PostgreSQL casts the column to `text`, as Laravel's own `like` does,
  so searching a uuid or integer column no longer throws `SQLSTATE[42883]`.
- `bindFromConfig()` with a class (not interface) contract no longer recurses until PHP
  crashes. Config naming the contract itself, or an abstract class, throws
  `InvalidConfigurationException`, and a class contract can default to itself.
- Packages published in one `vendor:publish` run no longer share migration timestamps, so
  their migrations run in publish order instead of interleaving by name.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- A fluent package-bootstrap builder: extend `PackageServiceProvider` and declare config,
  migrations, translations, views, routes, commands, facade aliases and an `about` section in
  `configurePackage()`. Route and alias config switches are read strictly: env-style booleans
  (`'off'`, `'0'`, `'no'` switch them off), and a typo such as `'disabled'` throws at boot
  instead of leaving the feature on.
- Publish-only migrations, timestamp-injected in directory order and republished in place —
  never over a same-named migration the package did not publish.
- Register-time helpers `bindFromConfig()` (a configured class that is missing or not the
  contract throws) and `observesModel()`, plus the idempotent
  `InteractsWithGates`, `RegistersBladeDirectives` and `RegistersBlueprintMacros` traits.
- A config-driven `KeyType` (`bigint` / `uuid` / `ulid`; an unrecognized value throws) with the
  `ownerKey()`, `morphKey()`, `auditable()` and `polymorphicSubject()` Blueprint macros.
- An injection-safe `whereLikeEscaped()` query macro that works on every Laravel database
  driver, and a `DatabaseDriver` enum.
- Strict config readers — `Config::integer()` (canonical integer strings only, optional
  bounds), `requireString()`, `enum()` (optional default), `oneOf()` and `boolean()`: the
  default applies only to a key that is not set (absent, null, or blank — `''` / whitespace,
  a host's `KEY=`), and any other invalid value throws a message naming the key and the value. `Config::for()` / `using()` validate a DTO's input array or
  throw your own exception class.
- `ModelResolver` to resolve model classes named in config, refusing any class that isn't the
  packaged model or a subclass of it (or an explicitly widened base).
- A PHPStan extension that declares the Blueprint and query macros for static analysis.
