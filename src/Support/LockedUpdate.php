<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Runs a pessimistic, atomic read-modify-write against a single model row.
 */
final class LockedUpdate
{
    /**
     * Re-fetch `$model` under a `lockForUpdate()` inside a transaction, apply
     * `$mutator` to the locked row, persist, and return the fresh instance.
     * This is the compare-and-set pattern that prevents lost updates on
     * concurrent writes. On SQLite the row lock is a no-op, but the transaction
     * and re-fetch still hold, so the pattern is portable.
     *
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @param  Closure(TModel): void  $mutator
     * @return TModel
     */
    public static function run(Model $model, Closure $mutator): Model
    {
        return $model->getConnection()->transaction(static function () use ($model, $mutator) {
            $locked = $model->newQuery()->lockForUpdate()->whereKey($model->getKey())->firstOrFail();

            // Sync the passed instance to the freshly-locked row, then mutate and
            // persist it, so the returned model keeps its concrete type.
            $model->setRawAttributes($locked->getAttributes(), sync: true);

            $mutator($model);

            $model->save();

            return $model;
        });
    }
}
