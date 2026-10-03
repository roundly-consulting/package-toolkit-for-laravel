# Changelog

All notable changes to `package-toolkit-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

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
