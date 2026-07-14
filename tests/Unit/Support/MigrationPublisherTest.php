<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\PackageToolkit\Support\MigrationPublisher;

beforeEach(function (): void {
    $this->migrations = sys_get_temp_dir().'/toolkit-migrations-'.bin2hex(random_bytes(6));

    mkdir($this->migrations);
});

afterEach(function (): void {
    foreach (glob($this->migrations.'/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($this->migrations);
});

it('derives the migration name from a package source file', function (string $path, string $expected): void {
    expect(MigrationPublisher::nameFor($path))->toBe($expected);
})->with([
    ['/pkg/database/migrations/create_widgets_table.php', 'create_widgets_table'],
    ['/pkg/database/migrations/create_widgets_table.php.stub', 'create_widgets_table'],
    ['/pkg/database/migrations/2020_01_01_000000_create_widgets_table.php', 'create_widgets_table'],
    ['/pkg/database/migrations/0001_01_01_000000_create_widgets_table.php', 'create_widgets_table'],
    ['create_widgets_table', 'create_widgets_table'],
]);

it('publishes to a freshly timestamped file when the migration was never published', function (): void {
    $destination = MigrationPublisher::destination(
        'create_widgets_table',
        $this->migrations,
        Carbon::parse('2026-07-14 20:15:30'),
    );

    expect($destination)->toBe($this->migrations.'/2026_07_14_201530_create_widgets_table.php');
});

it('reuses the already-published file so a second publish never duplicates a migration', function (): void {
    touch($existing = $this->migrations.'/2026_01_02_030405_create_widgets_table.php');

    $destination = MigrationPublisher::destination(
        'create_widgets_table',
        $this->migrations,
        Carbon::parse('2026-07-14 20:15:30'),
    );

    expect($destination)->toBe($existing);
});

it('reuses a file published verbatim, without a timestamp', function (): void {
    touch($existing = $this->migrations.'/create_widgets_table.php');

    expect(MigrationPublisher::destination('create_widgets_table', $this->migrations, Carbon::now()))
        ->toBe($existing);
});

it('ignores the host migrations that are not the one being published', function (): void {
    touch($this->migrations.'/2019_08_19_000000_create_failed_jobs_table.php');
    touch($this->migrations.'/2026_01_02_030405_create_widget_settings_table.php');

    expect(MigrationPublisher::destination('create_widgets_table', $this->migrations, Carbon::parse('2026-07-14 20:15:30')))
        ->toBe($this->migrations.'/2026_07_14_201530_create_widgets_table.php');
});

it('resolves a destination when the host migrations directory does not exist yet', function (): void {
    expect(MigrationPublisher::destination('create_widgets_table', $this->migrations.'/nope/', Carbon::parse('2026-07-14 20:15:30')))
        ->toBe($this->migrations.'/nope/2026_07_14_201530_create_widgets_table.php');
});
