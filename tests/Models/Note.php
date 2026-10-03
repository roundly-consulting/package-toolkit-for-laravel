<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A packaged model a host may extend, to prove the resolver accepts the packaged class and
 * its subclasses — and refuses any other model.
 */
class Note extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}
