<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Gearbox;

use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

/**
 * A second fixture package that publishes migrations, so a test can boot two providers
 * the way one `vendor:publish` run does and compare their timestamps.
 */
final class GearboxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('gearbox')->hasMigrations();
    }

    protected function resolvePackageBasePath(): string
    {
        return __DIR__;
    }
}
