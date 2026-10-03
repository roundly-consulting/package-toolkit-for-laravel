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
- Register-time helpers `bindFromConfig()` and `observesModel()`, plus the idempotent
  `InteractsWithGates`, `RegistersBladeDirectives` and `RegistersBlueprintMacros` traits.
- A config-driven `KeyType` (`bigint` / `uuid` / `ulid`) with the `ownerKey()`, `morphKey()`,
  `auditable()` and `polymorphicSubject()` Blueprint macros.
- An injection-safe `whereLikeEscaped()` query macro that works on every Laravel database
  driver, and a `DatabaseDriver` enum.
- Validate-or-throw config accessors (`Config::intBetween()`, `requireString()`, `enum()`,
  `boolean()` — env-style booleans, where a typo throws instead of reading as the default) and
  the lenient fall-back `enumOr()`, coercing env strings to the expected type, including
  `Config::for()` to validate a DTO's input array with your own exception class.
- `ModelResolver` to resolve and validate model classes named in config.
- A PHPStan extension that declares the Blueprint and query macros for static analysis.
