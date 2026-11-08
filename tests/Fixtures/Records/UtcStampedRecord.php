<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Records;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\PackageToolkit\Concerns\StoresUtcTimestamps;

/**
 * A package model whose timestamps and soft deletes are written in UTC, whatever
 * `app.timezone` says.
 *
 * @property int $id
 * @property string $name
 */
final class UtcStampedRecord extends Model
{
    use SoftDeletes;
    use StoresUtcTimestamps;

    protected $table = 'stamped_records';

    protected $guarded = [];
}
