<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Convenience wrapper around {@see ModelResolver} for classes that resolve
 * several models from config.
 */
trait ResolvesModels
{
    /**
     * The validated model class-string configured at `$key`: `$default` when
     * absent, otherwise `$base` (defaulting to `$default`) or a subclass of it.
     *
     * @template TModel of Model = Model
     *
     * @param  class-string<TModel>|null  $default
     * @param  class-string<TModel>|null  $base
     * @return class-string<TModel>
     */
    protected function modelClass(string $key, ?string $default = null, ?string $base = null): string
    {
        return ModelResolver::for($key, $default, $base);
    }

    /**
     * A fresh instance of the model configured at `$key`.
     *
     * @template TModel of Model = Model
     *
     * @param  class-string<TModel>|null  $default
     * @param  class-string<TModel>|null  $base
     * @return TModel
     */
    protected function newModel(string $key, ?string $default = null, ?string $base = null): Model
    {
        return ModelResolver::newModel($key, $default, $base);
    }
}
