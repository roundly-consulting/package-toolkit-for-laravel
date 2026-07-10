<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Std\Src;

use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

/**
 * Fixture whose provider lives at `<pkg>/src/` and does NOT override the base
 * path, so it exercises the default reflection-based resolution.
 */
final class StdServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('std')->hasConfigFile();
    }
}
