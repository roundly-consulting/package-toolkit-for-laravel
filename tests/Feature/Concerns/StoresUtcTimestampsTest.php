<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Records\LocalStampedRecord;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Records\UtcStampedRecord;

/*
 * Runs on the configured test engine (sqlite, and Postgres on the pgsql leg). The host is on
 * America/New_York, five hours behind UTC on the frozen instant: a column written in the host's
 * zone would read 01:30, one written in UTC reads 06:30.
 */

beforeEach(function (): void {
    $this->previousTimezone = date_default_timezone_get();
    config()->set('app.timezone', 'America/New_York');
    date_default_timezone_set('America/New_York');

    Carbon::setTestNow(CarbonImmutable::parse('2026-03-08 06:30:15.123456', 'UTC'));

    Schema::create('stamped_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
        $table->softDeletes();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('stamped_records');
    Carbon::setTestNow();
    Date::useDefault();
    date_default_timezone_set($this->previousTimezone);
});

/**
 * The stored column exactly as the engine hands it back, with no cast in between.
 */
function storedStamp(int $id, string $column): ?string
{
    $value = (new UtcStampedRecord)->getConnection()->table('stamped_records')->where('id', $id)->value($column);

    return $value === null ? null : substr((string) $value, 0, 19);
}

it('writes created_at and updated_at in UTC, whatever the host timezone', function (): void {
    $record = UtcStampedRecord::query()->create(['name' => 'utc']);

    expect(storedStamp($record->id, 'created_at'))->toBe('2026-03-08 06:30:15')
        ->and(storedStamp($record->id, 'updated_at'))->toBe('2026-03-08 06:30:15');
});

it('differs from what Laravel writes without it, in the host timezone', function (): void {
    $record = LocalStampedRecord::query()->create(['name' => 'local']);

    expect(storedStamp($record->id, 'created_at'))->toBe('2026-03-08 01:30:15');
});

it('round-trips the instant when read back as UTC', function (): void {
    $record = UtcStampedRecord::query()->create(['name' => 'utc']);

    $read = CarbonImmutable::createFromFormat('Y-m-d H:i:s', (string) storedStamp($record->id, 'created_at'), 'UTC');

    expect($read?->equalTo(CarbonImmutable::parse('2026-03-08 06:30:15', 'UTC')))->toBeTrue()
        ->and($read?->setTimezone('America/New_York')->format('Y-m-d H:i'))->toBe('2026-03-08 01:30');
});

it('writes touches and soft deletes in UTC', function (): void {
    $record = UtcStampedRecord::query()->create(['name' => 'utc']);

    Carbon::setTestNow(CarbonImmutable::parse('2026-03-08 08:00:00', 'UTC'));
    $record->touch();
    $record->delete();

    expect(storedStamp($record->id, 'created_at'))->toBe('2026-03-08 06:30:15')
        ->and(storedStamp($record->id, 'updated_at'))->toBe('2026-03-08 08:00:00')
        ->and(storedStamp($record->id, 'deleted_at'))->toBe('2026-03-08 08:00:00');
});

it('changes only the zone: same instant, microseconds kept', function (): void {
    $fresh = (new UtcStampedRecord)->freshTimestamp();

    expect($fresh->getTimezone()->getName())->toBe('UTC')
        ->and($fresh->format('Y-m-d H:i:s.u'))->toBe('2026-03-08 06:30:15.123456')
        ->and((new UtcStampedRecord)->freshTimestampString())->toBe('2026-03-08 06:30:15');
});

it("uses the host's date class, immutable included", function (): void {
    Date::use(CarbonImmutable::class);

    expect((new UtcStampedRecord)->freshTimestamp())->toBeInstanceOf(CarbonImmutable::class);

    Date::use(DateFactory::DEFAULT_CLASS_NAME);

    expect((new UtcStampedRecord)->freshTimestamp())->toBeInstanceOf(Carbon::class);
});
