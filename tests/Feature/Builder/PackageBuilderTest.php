<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\ToolboxServiceProvider;

function toolboxPublishes(string $tag): array
{
    return ServiceProvider::pathsToPublish(ToolboxServiceProvider::class, $tag);
}

it('merges the package config file', function (): void {
    expect(config('toolbox.key_type'))->toBe('uuid');
});

it('never auto-loads the package migrations', function (): void {
    expect(app('migrator')->paths())->not->toContain(
        realpath(__DIR__.'/../../Fixtures/Toolbox/toolbox/database/migrations'),
    );
});

it('registers a publish group for the config with the right tag', function (): void {
    $paths = toolboxPublishes('toolbox-config');

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('config/toolbox.php')
        ->and(reset($paths))->toBe(config_path('toolbox.php'));
});

it('publishes every migration in the directory as a timestamped file, never the directory itself', function (): void {
    $paths = toolboxPublishes('toolbox-migrations');

    expect(array_values($paths))->not->toContain(database_path('migrations'))
        ->and(array_keys($paths))->toContain(
            realpath(__DIR__.'/../../Fixtures/Toolbox/toolbox/database/migrations/2020_01_01_000000_create_toolbox_things_table.php'),
            realpath(__DIR__.'/../../Fixtures/Toolbox/toolbox/database/migrations/create_toolbox_gizmos_table.php'),
        );

    foreach ($paths as $destination) {
        expect(dirname($destination))->toBe(database_path('migrations'))
            ->and(basename($destination))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_toolbox_\w+_table\.php$/');
    }
});

it('publishes the migrations in the package directory order, not alphabetically', function (): void {
    $destinations = array_values(toolboxPublishes('toolbox-migrations'));

    sort($destinations);

    expect(array_map(
        static fn (string $path): string => preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($path)) ?? '',
        $destinations,
    ))->toBe([
        // The source directory's order: `2020_01_01_000000_create_toolbox_things_table.php`,
        // `create_toolbox_gizmos_table.php`, then the declared stub. Sorting the
        // published names alphabetically would put gizmos first.
        'create_toolbox_things_table.php',
        'create_toolbox_gizmos_table.php',
        'create_toolbox_widgets_table.php',
    ]);
});

it('timestamps the published migration stub', function (): void {
    $paths = toolboxPublishes('toolbox-migrations');

    $stubSource = realpath(__DIR__.'/../../Fixtures/Toolbox/toolbox/database/migrations/create_toolbox_widgets_table.php.stub');

    expect($paths)->toHaveKey($stubSource)
        ->and(basename($paths[$stubSource]))
        ->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_toolbox_widgets_table\.php$/');
});

it('registers publish groups for translations and views', function (): void {
    expect(array_values(toolboxPublishes('toolbox-translations')))
        ->toContain($this->app->langPath('vendor/toolbox'))
        ->and(array_values(toolboxPublishes('toolbox-views')))
        ->toContain(resource_path('views/vendor/toolbox'));
});

it('registers publish groups for routes and generic stubs', function (): void {
    expect(array_values(toolboxPublishes('toolbox-routes')))
        ->toContain(base_path('routes/toolbox.php'))
        ->and(array_values(toolboxPublishes('toolbox-stubs')))
        ->toContain(base_path('stubs/toolbox'));
});

it('registers the config-gated facade alias', function (): void {
    expect(class_exists('Toolbox'))->toBeTrue()
        ->and(class_exists('Gadget'))->toBeTrue();
});

it('skips a facade alias whose config gate is disabled', function (): void {
    expect(class_exists('Widget'))->toBeFalse();
});

it('loads an enabled route and skips a disabled one', function (): void {
    expect(Route::has('toolbox.ping'))->toBeTrue()
        ->and(Route::has('toolbox.disabled'))->toBeFalse();
});

it('registers the package views', function (): void {
    expect(View::exists('toolbox::widget'))->toBeTrue();
});

it('registers the package translations', function (): void {
    expect(trans('toolbox::messages.hello'))->toBe('Hello from toolbox');
});

it('registers the package command', function (): void {
    expect(Artisan::all())->toHaveKey('toolbox:demo');

    $this->artisan('toolbox:demo')->assertSuccessful();
});

it('contributes a section to the about command', function (): void {
    Artisan::call('about', ['--json' => true]);

    expect(strtolower(Artisan::output()))->toContain('toolbox');
});

it('binds a contract from config through the register helper', function (): void {
    expect(app(RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\Greeter::class)->greet())->toBe('hello');
});

it('registers a model observer through the register helper', function (): void {
    RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\ArticleObserver::$created = 0;

    RoundlyConsulting\PackageToolkit\Tests\Models\Article::create(['title' => 'observed']);

    expect(RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\ArticleObserver::$created)->toBe(1);
});
