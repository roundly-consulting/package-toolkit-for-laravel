<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit;

use Closure;
use RoundlyConsulting\PackageToolkit\Declarations\AboutDeclaration;
use RoundlyConsulting\PackageToolkit\Declarations\ConfigFileDeclaration;
use RoundlyConsulting\PackageToolkit\Declarations\FacadeAliasDeclaration;
use RoundlyConsulting\PackageToolkit\Declarations\MigrationStubDeclaration;
use RoundlyConsulting\PackageToolkit\Declarations\RouteDeclaration;
use RoundlyConsulting\PackageToolkit\Declarations\StubDeclaration;

/**
 * Fluent description of what a package ships: its config, migrations, views,
 * translations, routes, commands, aliases, and `about` contributions. The
 * {@see PackageServiceProvider} reads this and emits every `runningInConsole()`
 * gated publish/load/commands call once, so consumers never hand-write that
 * boilerplate.
 */
final class Package
{
    public string $name = '';

    public string $basePath = '';

    /** @var list<ConfigFileDeclaration> */
    public array $configFiles = [];

    public bool $hasMigrations = false;

    /** @var list<MigrationStubDeclaration> */
    public array $migrationStubs = [];

    public bool $hasTranslations = false;

    public bool $hasViews = false;

    public ?string $viewNamespace = null;

    /** @var list<RouteDeclaration> */
    public array $routes = [];

    /** @var list<class-string> */
    public array $commands = [];

    /** @var list<FacadeAliasDeclaration> */
    public array $facadeAliases = [];

    /** @var list<AboutDeclaration> */
    public array $aboutContributions = [];

    /** @var list<StubDeclaration> */
    public array $publishableStubs = [];

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setBasePath(string $basePath): self
    {
        $this->basePath = $basePath;

        return $this;
    }

    /**
     * Merge and publish a config file. Defaults to `<name>.php` (merged under
     * the `<name>` key, published as `<name>-config`); pass a bare filename to
     * override.
     */
    public function hasConfigFile(?string $file = null): self
    {
        $handle = $file !== null ? $this->stripPhpExtension($file) : $this->name;

        $this->configFiles[] = new ConfigFileDeclaration(
            fileName: $handle.'.php',
            key: $handle,
            tag: $this->name.'-config',
        );

        return $this;
    }

    /**
     * Load the package's `database/migrations` directory and publish it under
     * the `<name>-migrations` tag.
     */
    public function hasMigrations(): self
    {
        $this->hasMigrations = true;

        return $this;
    }

    /**
     * Publish a single `database/migrations/<name>.php.stub`, timestamp-injected
     * on publish, under the `<name>-migrations` tag.
     */
    public function hasMigration(string $name): self
    {
        $this->migrationStubs[] = new MigrationStubDeclaration(
            name: $name,
            tag: $this->name.'-migrations',
        );

        return $this;
    }

    /**
     * Load and publish the package's translations under the `<name>-translations`
     * tag (published to `lang/vendor/<name>`).
     */
    public function hasTranslations(): self
    {
        $this->hasTranslations = true;

        return $this;
    }

    /**
     * Load and publish the package's Blade views under the `<name>-views` tag,
     * registered in the `<namespace>` view namespace (defaults to `<name>`).
     */
    public function hasViews(?string $namespace = null): self
    {
        $this->hasViews = true;
        $this->viewNamespace = $namespace ?? $this->name;

        return $this;
    }

    /**
     * Load a route file (optionally gated behind a boolean config key) and
     * publish it under the `<name>-routes` tag.
     */
    public function hasRoutes(string $file, ?string $enabledVia = null): self
    {
        $this->routes[] = new RouteDeclaration(
            file: $file,
            tag: $this->name.'-routes',
            enabledVia: $enabledVia,
        );

        return $this;
    }

    /**
     * Register console commands (only when running in the console).
     *
     * @param  list<class-string>  $commands
     */
    public function hasCommands(array $commands): self
    {
        $this->commands = [...$this->commands, ...$commands];

        return $this;
    }

    /**
     * Register a class alias, optionally gated behind a boolean config key. The
     * alias name is the class's base name.
     *
     * @param  class-string  $class
     */
    public function hasFacadeAlias(string $class, ?string $configKey = null): self
    {
        $this->facadeAliases[] = new FacadeAliasDeclaration(
            class: $class,
            alias: class_basename($class),
            configKey: $configKey,
        );

        return $this;
    }

    /**
     * Contribute a section to `php artisan about`. The optional callback returns
     * the label/value pairs to show; the default reports the package name.
     *
     * @param  (Closure(): array<string, string>)|null  $data
     */
    public function contributesToAbout(?Closure $data = null): self
    {
        $name = $this->name;

        $this->aboutContributions[] = new AboutDeclaration(
            section: ucfirst($name),
            data: $data ?? static fn (): array => ['Package' => $name],
        );

        return $this;
    }

    /**
     * Publish an arbitrary set of stub files under a custom tag (a generic
     * escape hatch for assets that don't fit the other helpers).
     */
    public function publishesStubs(string $from, string $to, string $tag): self
    {
        $this->publishableStubs[] = new StubDeclaration(
            from: $from,
            to: $to,
            tag: $tag,
        );

        return $this;
    }

    public function configPath(string $file): string
    {
        return $this->basePath.'/config/'.$file;
    }

    public function migrationsPath(): string
    {
        return $this->basePath.'/database/migrations';
    }

    public function migrationStubPath(string $name): string
    {
        return $this->basePath.'/database/migrations/'.$name.'.php.stub';
    }

    public function translationsPath(): string
    {
        return $this->basePath.'/resources/lang';
    }

    public function viewsPath(): string
    {
        return $this->basePath.'/resources/views';
    }

    public function routesPath(string $file): string
    {
        return $this->basePath.'/routes/'.$file;
    }

    private function stripPhpExtension(string $file): string
    {
        return preg_replace('/\.php$/', '', $file) ?? $file;
    }
}
