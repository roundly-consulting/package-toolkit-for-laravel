<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit;

use Illuminate\Container\Container;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use RoundlyConsulting\PackageToolkit\Declarations\FacadeAliasDeclaration;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\PackageToolkit\Support\MigrationPublisher;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Base service provider for roundly `*-for-laravel` packages: a native,
 * dependency-free package bootstrapper. Subclasses
 * declare what the package ships in {@see self::configurePackage()}; this base
 * emits every `runningInConsole()`-gated publish/load/commands call once.
 *
 * `register()` stays overridable for bespoke container bindings — override it
 * and call `parent::register()` first.
 */
abstract class PackageServiceProvider extends ServiceProvider
{
    protected Package $package;

    /**
     * Declare the package's config, migrations, views, translations, routes,
     * commands, aliases, and `about` contributions.
     */
    abstract public function configurePackage(Package $package): void;

    public function register(): void
    {
        $this->package = (new Package)->setBasePath($this->resolvePackageBasePath());

        $this->configurePackage($this->package);

        $this->registerPackageConfig();
        $this->registerFacadeAliases();
    }

    public function boot(): void
    {
        $this->bootPackage();
    }

    /**
     * The package root (the directory that holds `config/`, `database/`,
     * `resources/`, `routes/`). Resolved from the provider's own location:
     * `src/<Provider>.php` → package root. Override for non-standard layouts.
     */
    protected function resolvePackageBasePath(): string
    {
        return dirname((string) (new ReflectionClass(static::class))->getFileName(), 2);
    }

    protected function registerPackageConfig(): void
    {
        foreach ($this->package->configFiles as $config) {
            $this->mergeConfigFrom($this->package->configPath($config->fileName), $config->key);
        }
    }

    protected function registerFacadeAliases(): void
    {
        foreach ($this->package->facadeAliases as $alias) {
            $name = $this->resolveAliasName($alias);

            if ($name === null) {
                continue;
            }

            AliasLoader::getInstance()->alias($name, $alias->class);
        }
    }

    /**
     * The alias name to register for a declaration, or `null` to skip it. The
     * config value (when the declaration names one) is read strictly, via
     * {@see Config::boolean()}: an explicit `null`, or a false spelling (`false`,
     * `0`, `'0'`, `'false'`, `'off'`, `'no'`) skips aliasing; a true spelling
     * (`true`, `1`, `'1'`, `'true'`, `'on'`, `'yes'`), an absent key or a blank
     * value (`''`, whitespace — not set) uses the declared default (the facade's
     * base name); any other non-empty string
     * renames the alias. Anything else (`2`, `1.5`, an array) throws
     * {@see InvalidConfigurationException} instead of quietly aliasing.
     *
     * @throws InvalidConfigurationException
     */
    protected function resolveAliasName(FacadeAliasDeclaration $alias): ?string
    {
        if ($alias->configKey === null) {
            return $alias->alias;
        }

        // An absent key defaults to `true`, so an explicit `null` still opts out.
        $configured = config($alias->configKey, true);

        if ($configured === null) {
            return null;
        }

        // A string that is not an env-style boolean is the alias name itself.
        if (is_string($configured) && filter_var($configured, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === null) {
            return $configured;
        }

        return Config::boolean($alias->configKey, true) ? $alias->alias : null;
    }

    protected function bootPackage(): void
    {
        if ($this->package->hasTranslations) {
            $this->loadTranslationsFrom($this->package->translationsPath(), $this->package->name);
        }

        if ($this->package->hasViews) {
            $this->loadViewsFrom($this->package->viewsPath(), $this->package->viewNamespace ?? $this->package->name);
        }

        foreach ($this->package->routes as $route) {
            // Parsed strictly: `'off'` / `'0'` switch the route off, and a typo such as
            // `'disabled'` throws rather than failing open by loading the routes.
            if ($route->enabledVia !== null && ! Config::boolean($route->enabledVia, true)) {
                continue;
            }

            // A host that published the route file edits that copy, so it replaces the
            // package's file rather than registering beside it.
            $published = base_path('routes/'.$route->file);

            $this->loadRoutesFrom(is_file($published) ? $published : $this->package->routesPath($route->file));
        }

        $this->registerAboutContributions();

        if ($this->app->runningInConsole()) {
            $this->bootPackageInConsole();
        }
    }

    protected function registerAboutContributions(): void
    {
        if (! class_exists(AboutCommand::class)) {
            return;
        }

        foreach ($this->package->aboutContributions as $contribution) {
            AboutCommand::add($contribution->section, $contribution->data);
        }
    }

    protected function bootPackageInConsole(): void
    {
        foreach ($this->package->configFiles as $config) {
            $this->publishes([
                $this->package->configPath($config->fileName) => config_path($config->fileName),
            ], $config->tag);
        }

        $this->publishPackageMigrations();

        if ($this->package->hasTranslations) {
            $this->publishes([
                $this->package->translationsPath() => $this->app->langPath('vendor/'.$this->package->name),
            ], $this->package->name.'-translations');
        }

        if ($this->package->hasViews) {
            $this->publishes([
                $this->package->viewsPath() => resource_path('views/vendor/'.($this->package->viewNamespace ?? $this->package->name)),
            ], $this->package->name.'-views');
        }

        foreach ($this->package->routes as $route) {
            $this->publishes([
                $this->package->routesPath($route->file) => base_path('routes/'.$route->file),
            ], $route->tag);
        }

        foreach ($this->package->publishableStubs as $stub) {
            $this->publishes([$stub->from => $stub->to], $stub->tag);
        }

        if ($this->package->commands !== []) {
            $this->commands($this->package->commands);
        }
    }

    /**
     * Register the publish groups for the package's migrations.
     *
     * Migrations are **publish-only** — nothing is loaded from the package, so a
     * host's `php artisan migrate` runs exactly the files it published. Each file
     * is published under a `<Y_m_d_His>_<name>.php` filename so it orders against
     * the host's own migrations; the timestamps step forward one second per file,
     * preserving the package directory's order (a package's migrations often
     * depend on each other's tables). Republishing lands on the file it landed on
     * last time, so a second publish overwrites in place instead of creating a
     * duplicate migration — and only that file: a same-named host migration with
     * different contents is never a destination (see {@see MigrationPublisher}).
     */
    protected function publishPackageMigrations(): void
    {
        $directory = database_path('migrations');

        $sources = $this->package->hasMigrations ? $this->package->migrationFiles() : [];

        foreach ($sources as $file) {
            $this->publishes([
                $file => MigrationPublisher::destination(
                    $file,
                    $directory,
                    MigrationPublisher::nextTimestamp(),
                ),
            ], $this->package->name.'-migrations');
        }

        foreach ($this->package->migrationStubs as $stub) {
            $source = $this->package->migrationStubPath($stub->name);

            $this->publishes([
                $source => MigrationPublisher::destination(
                    $source,
                    $directory,
                    MigrationPublisher::nextTimestamp(),
                ),
            ], $stub->tag);
        }
    }

    /**
     * Bind a contract to the implementation named by a config key. Call from an
     * overridden `register()`. Config is read when the contract is resolved: an
     * absent, null or blank value binds `$default`; any other value must name an
     * existing, instantiable class that is a `$contract`, or resolving THROWS
     * {@see InvalidConfigurationException} (so `false`, `''`, a missing class, a
     * class of the wrong type, an abstract class, or the contract itself never
     * resolve silently). A class contract may name itself as `$default`.
     *
     * @param  class-string  $contract
     * @param  class-string  $default
     */
    protected function bindFromConfig(string $contract, string $configKey, string $default): void
    {
        $this->app->bind($contract, function (Container $container) use ($contract, $configKey, $default): mixed {
            $configured = config($configKey);

            // Blank (`''`, whitespace — a host's `KEY=`) is not set, exactly like absent.
            if ($configured === null || (is_string($configured) && trim($configured) === '')) {
                // make() on the contract itself would re-enter this very binding.
                return self::namesClass($default, $contract) ? $container->build($default) : $container->make($default);
            }

            if (! is_string($configured)
                || ! class_exists($configured)
                || ! is_a($configured, $contract, true)
                || self::namesClass($configured, $contract)
                || ! (new ReflectionClass($configured))->isInstantiable()) {
                throw InvalidConfigurationException::notAnImplementation($configKey, $contract, $configured);
            }

            return $container->make($configured);
        });
    }

    /**
     * Whether `$name` spells the class `$class` (class names are case-insensitive,
     * and a leading backslash is optional).
     */
    private static function namesClass(string $name, string $class): bool
    {
        return strcasecmp(ltrim($name, '\\'), ltrim($class, '\\')) === 0;
    }

    /**
     * Register a model observer against the model class named by a config key.
     * Call from an overridden `boot()`.
     *
     * @param  class-string  $observer
     */
    protected function observesModel(string $configKey, string $observer): void
    {
        ModelResolver::for($configKey)::observe($observer);
    }
}
