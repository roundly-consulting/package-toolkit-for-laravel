<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\ToolboxServiceProvider;

/**
 * `vendor:publish` end to end, against a throwaway host `database/` directory. A
 * provider resolves its publish destinations when it boots, so each "fresh process"
 * below is a re-register + re-boot of the fixture provider.
 */
beforeEach(function (): void {
    $this->hostDatabase = sys_get_temp_dir().'/toolkit-host-db-'.bin2hex(random_bytes(6));

    mkdir($this->hostDatabase.'/migrations', 0777, true);

    $this->originalDatabasePath = $this->app->databasePath();
    $this->app->useDatabasePath($this->hostDatabase);

    $this->source = realpath(__DIR__.'/../../Fixtures/Toolbox/toolbox/database/migrations/create_toolbox_gizmos_table.php');

    $this->bootToolbox = function (): void {
        $provider = new ToolboxServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    };

    $this->publish = function (bool $force = false): void {
        ($this->bootToolbox)();

        Artisan::call('vendor:publish', ['--tag' => 'toolbox-migrations', '--force' => $force]);
    };

    $this->gizmoMigrations = fn (): array => glob($this->hostDatabase.'/migrations/*_create_toolbox_gizmos_table.php') ?: [];
});

afterEach(function (): void {
    foreach (glob($this->hostDatabase.'/migrations/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($this->hostDatabase.'/migrations');
    rmdir($this->hostDatabase);

    $this->app->useDatabasePath($this->originalDatabasePath);
});

it('never overwrites a host migration that merely shares the name', function (): void {
    $host = $this->hostDatabase.'/migrations/2019_05_05_000000_create_toolbox_gizmos_table.php';
    file_put_contents($host, "<?php // the host's own gizmos migration\n");

    ($this->publish)(force: true);

    expect(file_get_contents($host))->toBe("<?php // the host's own gizmos migration\n");
});

it('publishes the package migration beside a same-named host migration instead of skipping it', function (): void {
    $host = $this->hostDatabase.'/migrations/2019_05_05_000000_create_toolbox_gizmos_table.php';
    file_put_contents($host, "<?php // the host's own gizmos migration\n");

    ($this->publish)();

    $published = array_values(array_diff(($this->gizmoMigrations)(), [$host]));

    expect($published)->toHaveCount(1)
        ->and(file_get_contents($published[0]))->toBe(file_get_contents($this->source));
});

it('republishes in place, even with a same-named host migration beside it', function (): void {
    file_put_contents($this->hostDatabase.'/migrations/2019_05_05_000000_create_toolbox_gizmos_table.php', "<?php // host\n");

    ($this->publish)();
    ($this->publish)(force: true);
    ($this->publish)();

    expect(($this->gizmoMigrations)())->toHaveCount(2);
});

it('republishes an unchanged migration in place rather than duplicating it', function (): void {
    ($this->publish)();

    $first = ($this->gizmoMigrations)();

    $this->travel(1)->hours();

    ($this->publish)(force: true);

    expect(($this->gizmoMigrations)())->toBe($first)->toHaveCount(1);
});
