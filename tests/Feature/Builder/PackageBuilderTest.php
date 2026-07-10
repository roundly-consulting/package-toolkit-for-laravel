<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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

it('loads the package migrations', function (): void {
    expect(Schema::hasTable('toolbox_things'))->toBeTrue();
});

it('registers a publish group for the config with the right tag', function (): void {
    $paths = toolboxPublishes('toolbox-config');

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('config/toolbox.php')
        ->and(reset($paths))->toBe(config_path('toolbox.php'));
});

it('registers a publish group for the migrations directory', function (): void {
    $paths = toolboxPublishes('toolbox-migrations');

    expect(array_values($paths))->toContain(database_path('migrations'));
});

it('timestamps the single published migration stub', function (): void {
    $paths = toolboxPublishes('toolbox-migrations');

    $stubDest = collect($paths)->first(
        fn (string $dest): bool => str_ends_with($dest, '_create_toolbox_widgets_table.php'),
    );

    expect($stubDest)->not->toBeNull()
        ->and(basename($stubDest))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_toolbox_widgets_table\.php$/');
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
