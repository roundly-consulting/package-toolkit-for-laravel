<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\PackageToolkit\Support\MigrationPublisher;

const WIDGETS_MIGRATION = "<?php\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('widgets', fn (Blueprint \$table) => \$table->id());\n    }\n};\n";

beforeEach(function (): void {
    $this->migrations = sys_get_temp_dir().'/toolkit-migrations-'.bin2hex(random_bytes(6));
    $this->package = sys_get_temp_dir().'/toolkit-package-'.bin2hex(random_bytes(6));

    mkdir($this->migrations);
    mkdir($this->package);

    file_put_contents($this->source = $this->package.'/create_widgets_table.php', WIDGETS_MIGRATION);
});

afterEach(function (): void {
    foreach ([$this->migrations, $this->package] as $directory) {
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($directory);
    }
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
        $this->source,
        $this->migrations,
        Carbon::parse('2026-07-14 20:15:30'),
    );

    expect($destination)->toBe($this->migrations.'/2026_07_14_201530_create_widgets_table.php');
});

it('publishes under the source name without the package timestamp or stub extension', function (): void {
    file_put_contents($stub = $this->package.'/2020_01_01_000000_create_widgets_table.php.stub', WIDGETS_MIGRATION);

    expect(MigrationPublisher::destination($stub, $this->migrations, Carbon::parse('2026-07-14 20:15:30')))
        ->toBe($this->migrations.'/2026_07_14_201530_create_widgets_table.php');
});

it('reuses the already-published file so a second publish never duplicates a migration', function (): void {
    file_put_contents($existing = $this->migrations.'/2026_01_02_030405_create_widgets_table.php', WIDGETS_MIGRATION);

    $destination = MigrationPublisher::destination(
        $this->source,
        $this->migrations,
        Carbon::parse('2026-07-14 20:15:30'),
    );

    expect($destination)->toBe($existing);
});

it('reuses a file published verbatim, without a timestamp', function (): void {
    file_put_contents($existing = $this->migrations.'/create_widgets_table.php', WIDGETS_MIGRATION);

    expect(MigrationPublisher::destination($this->source, $this->migrations, Carbon::now()))
        ->toBe($existing);
});

it('still recognises its copy after a line-ending or re-indentation change', function (): void {
    $reformatted = str_replace(["\n", '    '], ["\r\n", "\t"], WIDGETS_MIGRATION);
    file_put_contents($existing = $this->migrations.'/2026_01_02_030405_create_widgets_table.php', $reformatted);

    expect(MigrationPublisher::destination($this->source, $this->migrations, Carbon::now()))
        ->toBe($existing);
});

it('never resolves to a same-named host migration with different contents', function (): void {
    // The host's own `create_widgets_table` (or another package's): reusing it would
    // make `vendor:publish` skip the package migration and `--force` overwrite the host's.
    file_put_contents($this->migrations.'/2019_05_05_000000_create_widgets_table.php', "<?php // the host's own\n");

    expect(MigrationPublisher::destination($this->source, $this->migrations, Carbon::parse('2026-07-14 20:15:30')))
        ->toBe($this->migrations.'/2026_07_14_201530_create_widgets_table.php');
});

it('finds its own copy among same-named migrations the host owns', function (): void {
    file_put_contents($this->migrations.'/2019_05_05_000000_create_widgets_table.php', "<?php // the host's own\n");
    file_put_contents($ours = $this->migrations.'/2026_01_02_030405_create_widgets_table.php', WIDGETS_MIGRATION);
    file_put_contents($this->migrations.'/2027_01_01_000000_create_widgets_table.php', "<?php // another package's\n");

    expect(MigrationPublisher::destination($this->source, $this->migrations, Carbon::now()))->toBe($ours);
});

it('publishes afresh when the package source cannot be read', function (): void {
    file_put_contents($this->migrations.'/2026_01_02_030405_create_widgets_table.php', WIDGETS_MIGRATION);

    expect(MigrationPublisher::destination($this->package.'/gone/create_widgets_table.php', $this->migrations, Carbon::parse('2026-07-14 20:15:30')))
        ->toBe($this->migrations.'/2026_07_14_201530_create_widgets_table.php');
});

it('ignores the host migrations that are not the one being published', function (): void {
    touch($this->migrations.'/2019_08_19_000000_create_failed_jobs_table.php');
    touch($this->migrations.'/2026_01_02_030405_create_widget_settings_table.php');

    expect(MigrationPublisher::destination($this->source, $this->migrations, Carbon::parse('2026-07-14 20:15:30')))
        ->toBe($this->migrations.'/2026_07_14_201530_create_widgets_table.php');
});

it('resolves a destination when the host migrations directory does not exist yet', function (): void {
    expect(MigrationPublisher::destination($this->source, $this->migrations.'/nope/', Carbon::parse('2026-07-14 20:15:30')))
        ->toBe($this->migrations.'/nope/2026_07_14_201530_create_widgets_table.php');
});

// Booting the providers already drew a timestamp from the real clock; a pinned
// clock behind it would only ever see that cursor plus one second.
it('hands out one timestamp per file, a second apart, while the clock stands still', function (): void {
    MigrationPublisher::resetTimestamps();
    Carbon::setTestNow('2026-10-05 20:00:00');

    expect([
        MigrationPublisher::nextTimestamp()->format('Y_m_d_His'),
        MigrationPublisher::nextTimestamp()->format('Y_m_d_His'),
        MigrationPublisher::nextTimestamp()->format('Y_m_d_His'),
    ])->toBe(['2026_10_05_200000', '2026_10_05_200001', '2026_10_05_200002']);
});

it('starts again from the current time once the clock has passed the cursor', function (): void {
    MigrationPublisher::resetTimestamps();
    Carbon::setTestNow('2026-10-05 20:00:00');
    MigrationPublisher::nextTimestamp();

    Carbon::setTestNow('2026-10-05 21:30:00.750');

    expect(MigrationPublisher::nextTimestamp()->format('Y_m_d_His.u'))->toBe('2026_10_05_213000.000000');
});

it('forgets the cursor on reset', function (): void {
    Carbon::setTestNow('2026-10-05 20:00:00');
    MigrationPublisher::nextTimestamp();
    MigrationPublisher::nextTimestamp();

    MigrationPublisher::resetTimestamps();

    expect(MigrationPublisher::nextTimestamp()->format('Y_m_d_His'))->toBe('2026_10_05_200000');
});
