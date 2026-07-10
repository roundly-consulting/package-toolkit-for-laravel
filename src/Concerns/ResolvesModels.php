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
     * The validated model class-string configured at `$key`.
     *
     * @return class-string<Model>
     */
    protected function modelClass(string $key, ?string $default = null): string
    {
        return ModelResolver::for($key, $default);
    }

    /**
     * A fresh instance of the model configured at `$key`.
     */
    protected function newModel(string $key, ?string $default = null): Model
    {
        return ModelResolver::newModel($key, $default);
    }
}
