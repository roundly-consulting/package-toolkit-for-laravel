<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $value
 */
final class Counter extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}
