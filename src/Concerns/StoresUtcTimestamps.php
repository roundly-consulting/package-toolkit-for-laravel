<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * Makes a package model's "now" UTC: Eloquent's `created_at` / `updated_at`, `touch()`, soft
 * deletes, and anything else written through `freshTimestamp()` / `freshTimestampString()`.
 * A host on `America/New_York` then stores the same instants as one on UTC.
 *
 * The zone is the only change from Laravel's own `freshTimestamp()`: the instant, its
 * microseconds and the date class (the host's `Date::use()`, immutable included) stay as they
 * were, so the column's precision is decided by the model's date format, as usual.
 *
 * Writing is half of it. Read the columns back as UTC too — a cast that parses them in UTC, or
 * a host on `app.timezone = UTC` — or Eloquent's default `datetime` cast parses the UTC wall
 * time in the host's zone.
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait StoresUtcTimestamps
{
    public function freshTimestamp(): CarbonInterface
    {
        return Date::now('UTC');
    }
}
