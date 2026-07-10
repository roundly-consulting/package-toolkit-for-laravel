<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Support;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * Resolves an Eloquent model class from a config key, validating that the
 * configured value is a real model class. Replaces the resolve-and-validate
 * dance repeated across packages that map `config('<pkg>.models.*')`.
 */
final class ModelResolver
{
    /**
     * The validated model class-string configured at `$key` (or `$default` when
     * the key is absent); throws when the value is not an existing model class.
     *
     * @return class-string<Model>
     */
    public static function for(string $key, ?string $default = null): string
    {
        $value = config($key) ?? $default;

        if (! is_string($value) || ! is_subclass_of($value, Model::class)) {
            throw InvalidConfigurationException::notAModel($key, $value);
        }

        return $value;
    }

    /**
     * A fresh instance of the model configured at `$key`.
     */
    public static function newModel(string $key, ?string $default = null): Model
    {
        $class = self::for($key, $default);

        return new $class;
    }
}
