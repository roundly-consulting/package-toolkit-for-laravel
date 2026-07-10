<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\ToolboxServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ToolboxServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/Toolbox/toolbox/database/migrations');

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
