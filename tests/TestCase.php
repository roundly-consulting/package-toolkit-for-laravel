<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\ToolboxServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * The toolkit ships no service provider of its own — it ships the *base* every other
     * package's provider extends. Toolbox is the fixture consumer that exercises the
     * builder end to end, so it stands in for the 45 real providers here.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [ToolboxServiceProvider::class];
    }

    /**
     * A literal directory, not a provider class: the base case resolves a provider to its
     * `database/migrations` by reflecting on the provider's own file location, and the
     * Toolbox fixture deliberately overrides `resolvePackageBasePath()` to point at its
     * nested `toolbox/` tree. Naming the provider here would resolve to the wrong path.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [__DIR__.'/Fixtures/Toolbox/toolbox/database/migrations'];
    }

    /**
     * Two host-owned fixture tables the toolkit's macros and model helpers act on. They
     * are ad-hoc `Schema::create()` calls rather than migrations on purpose — nothing here
     * is a shipped migration, so there is no order to pin.
     */
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        Schema::create('counters', function (Blueprint $table): void {
            $table->id();
            $table->integer('value')->default(0);
        });

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
        });
    }
}
