<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Records;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Concerns\HasConfigurableKey;

/**
 * A package model whose own primary key follows `toolbox.primary_key_type`, the way every
 * roundly model with an inbound key adopts the concern.
 *
 * @property int|string $id
 * @property string $name
 */
final class KeyedRecord extends Model
{
    use HasConfigurableKey;

    public $timestamps = false;

    protected $table = 'keyed_records';

    protected $guarded = [];

    protected function primaryKeyTypeConfigKey(): string
    {
        return 'toolbox.primary_key_type';
    }
}
