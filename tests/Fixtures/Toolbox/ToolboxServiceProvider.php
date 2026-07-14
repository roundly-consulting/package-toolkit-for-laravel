<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox;

use RoundlyConsulting\PackageToolkit\Concerns\InteractsWithGates;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBladeDirectives;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

/**
 * Fixture consumer package exercising every builder method and opt-in trait.
 */
final class ToolboxServiceProvider extends PackageServiceProvider
{
    use InteractsWithGates;
    use RegistersBladeDirectives;
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('toolbox')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasMigration('create_toolbox_widgets_table')
            ->hasTranslations()
            ->hasViews()
            ->hasRoutes('toolbox.php', 'toolbox.routes.enabled')
            ->hasRoutes('disabled.php', 'toolbox.routes.disabled')
            ->hasCommands([ToolboxCommand::class])
            ->hasFacadeAlias(Toolbox::class, 'toolbox.alias')
            ->hasFacadeAlias(Widget::class, 'toolbox.disabled_alias')
            ->hasFacadeAlias(Gadget::class)
            ->contributesToAbout()
            ->publishesStubs(
                $package->basePath.'/stubs',
                base_path('stubs/toolbox'),
                'toolbox-stubs',
            );
    }

    public function register(): void
    {
        parent::register();

        $this->bindFromConfig(Greeter::class, 'toolbox.greeter', EnglishGreeter::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->observesModel('toolbox.observed_model', ArticleObserver::class);
        $this->registerBlueprintMacros();
        $this->registerBladeDirective('toolboxHello', static fn (): string => "<?php echo 'hi from toolbox'; ?>");
        $this->registerBladeIf('toolboxEnabled', static fn (bool $flag): bool => $flag);
        $this->defineGate('toolbox-manage', static fn (mixed $user): bool => true);
    }

    protected function resolvePackageBasePath(): string
    {
        return __DIR__.'/toolbox';
    }
}
