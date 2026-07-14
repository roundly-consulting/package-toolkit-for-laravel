<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\PHPStan;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

/**
 * Makes the toolkit's query-builder macros visible to PHPStan.
 *
 * The schema macros are declared in a stub file, but both builder classes are
 * already described by Larastan's own stubs (only one stub per class is used),
 * so the query macros are contributed through a reflection extension instead.
 */
final class QueryMacrosExtension implements MethodsClassReflectionExtension
{
    /** The macros registered by RegistersBlueprintMacros on both builders. */
    private const MACROS = ['whereLikeEscaped'];

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (! in_array($methodName, self::MACROS, true)) {
            return false;
        }

        return $this->isBuilder($classReflection);
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return new QueryMacroMethodReflection($classReflection, $methodName);
    }

    private function isBuilder(ClassReflection $classReflection): bool
    {
        foreach ([QueryBuilder::class, EloquentBuilder::class] as $builder) {
            if ($classReflection->getName() === $builder || $classReflection->isSubclassOf($builder)) {
                return true;
            }
        }

        return false;
    }
}
