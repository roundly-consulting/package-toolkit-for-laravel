<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Std\Src\StdServiceProvider;

it('resolves the package base path from the provider location by default', function (): void {
    $this->app->register(StdServiceProvider::class);

    expect(config('std.enabled'))->toBeTrue();
});
