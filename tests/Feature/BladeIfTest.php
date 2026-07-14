<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('renders the truthy branch of a package conditional directive', function (): void {
    $output = Blade::render('@toolboxEnabled($flag) on @else off @endtoolboxEnabled', ['flag' => true]);

    expect(trim($output))->toBe('on');
});

it('renders the else branch of a package conditional directive', function (): void {
    $output = Blade::render('@toolboxEnabled($flag) on @else off @endtoolboxEnabled', ['flag' => false]);

    expect(trim($output))->toBe('off');
});

it('supports the unless form of a package conditional directive', function (): void {
    $output = Blade::render('@unlesstoolboxEnabled($flag) off @endtoolboxEnabled', ['flag' => false]);

    expect(trim($output))->toBe('off');
});
