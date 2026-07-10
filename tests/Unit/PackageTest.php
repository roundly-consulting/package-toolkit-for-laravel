<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Package;

it('infers config file, key and tag from the package name', function (): void {
    $package = (new Package)->name('widgets')->hasConfigFile();

    expect($package->configFiles)->toHaveCount(1)
        ->and($package->configFiles[0]->fileName)->toBe('widgets.php')
        ->and($package->configFiles[0]->key)->toBe('widgets')
        ->and($package->configFiles[0]->tag)->toBe('widgets-config');
});

it('accepts a custom config filename with or without the php extension', function (): void {
    $package = (new Package)->name('widgets')->hasConfigFile('services.php');

    expect($package->configFiles[0]->fileName)->toBe('services.php')
        ->and($package->configFiles[0]->key)->toBe('services');
});

it('defaults the view namespace to the package name and allows an override', function (): void {
    $default = (new Package)->name('widgets')->hasViews();
    $custom = (new Package)->name('widgets')->hasViews('ui');

    expect($default->viewNamespace)->toBe('widgets')
        ->and($custom->viewNamespace)->toBe('ui');
});

it('merges multiple command declarations into a single list', function (): void {
    $package = (new Package)->name('widgets')
        ->hasCommands(['A'])
        ->hasCommands(['B', 'C']);

    expect($package->commands)->toBe(['A', 'B', 'C']);
});

it('builds path helpers relative to the base path', function (): void {
    $package = (new Package)->name('widgets')->setBasePath('/pkg');

    expect($package->configPath('widgets.php'))->toBe('/pkg/config/widgets.php')
        ->and($package->migrationsPath())->toBe('/pkg/database/migrations')
        ->and($package->migrationStubPath('create_x'))->toBe('/pkg/database/migrations/create_x.php.stub')
        ->and($package->translationsPath())->toBe('/pkg/resources/lang')
        ->and($package->viewsPath())->toBe('/pkg/resources/views')
        ->and($package->routesPath('web.php'))->toBe('/pkg/routes/web.php');
});

it('uses the package name as the about section with a default resolver', function (): void {
    $package = (new Package)->name('widgets')->contributesToAbout();

    expect($package->aboutContributions[0]->section)->toBe('Widgets')
        ->and(($package->aboutContributions[0]->data)())->toBe(['Package' => 'widgets']);
});
