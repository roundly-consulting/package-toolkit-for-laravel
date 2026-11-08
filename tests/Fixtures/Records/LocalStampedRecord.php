<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Records;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\PackageToolkit\Concerns\StoresUtcTimestamps;

/**
 * The same table without the concern: Laravel's own timestamps, in `app.timezone`. The
 * control that shows what {@see StoresUtcTimestamps} changes.
 *
 * @property int $id
 * @property string $name
 */
final class LocalStampedRecord extends Model
{
    use SoftDeletes;

    protected $table = 'stamped_records';

    protected $guarded = [];
}
