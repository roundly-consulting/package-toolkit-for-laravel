<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Support;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * Resolves an Eloquent model class from a config key, validating that the
 * configured value is a model the package can use. Replaces the
 * resolve-and-validate dance repeated across packages that map
 * `config('<pkg>.models.*')`.
 */
final class ModelResolver
{
    /**
     * The model class configured at `$key`, or `$default` only when the key is
     * not set (absent, null or blank — `''` or whitespace). The class must exist, be an Eloquent model, and be `$base`
     * or a subclass of it — `$base` defaulting to `$default` (the packaged model a
     * host extends), else to {@see Model}. Anything else THROWS naming the key;
     * a wrong class never silently falls back to the packaged one. Pass
     * `base: Model::class` when `$default` is only a suggestion a host may replace
     * with an unrelated model.
     *
     * @template TModel of Model = Model
     *
     * @param  class-string<TModel>|null  $default
     * @param  class-string<TModel>|null  $base
     * @return class-string<TModel>
     *
     * @throws InvalidConfigurationException
     */
    public static function for(string $key, ?string $default = null, ?string $base = null): string
    {
        $base ??= $default ?? Model::class;
        $value = config($key);

        if (is_string($value) && trim($value) === '') {
            $value = null;
        }

        $value ??= $default;

        if (! is_string($value) || ! is_subclass_of($value, Model::class) || ! is_a($value, $base, true)) {
            throw InvalidConfigurationException::notAModel($key, $value, $base);
        }

        return $value;
    }

    /**
     * A fresh instance of the model configured at `$key` (see {@see self::for()}).
     *
     * @template TModel of Model = Model
     *
     * @param  class-string<TModel>|null  $default
     * @param  class-string<TModel>|null  $base
     * @return TModel
     *
     * @throws InvalidConfigurationException
     */
    public static function newModel(string $key, ?string $default = null, ?string $base = null): Model
    {
        $class = self::for($key, $default, $base);

        return new $class;
    }
}
