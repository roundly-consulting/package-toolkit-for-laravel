<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PHPStan\Reflection\PassedByReference;
use PHPStan\TrinaryLogic;
use PHPStan\Type\VerbosityLevel;
use RoundlyConsulting\PackageToolkit\PHPStan\QueryMacrosExtension;

it('reports the whereLikeEscaped macro on both builders', function (string $class): void {
    expect((new QueryMacrosExtension)->hasMethod(classReflection($class), 'whereLikeEscaped'))->toBeTrue();
})->with([[QueryBuilder::class], [EloquentBuilder::class]]);

it('reports nothing for a method the toolkit does not register', function (): void {
    expect((new QueryMacrosExtension)->hasMethod(classReflection(QueryBuilder::class), 'whereLikeUnescaped'))->toBeFalse();
});

it('reports nothing for a class that is not a builder', function (): void {
    expect((new QueryMacrosExtension)->hasMethod(classReflection(Model::class), 'whereLikeEscaped'))->toBeFalse();
});

it('describes the macro signature', function (): void {
    $declaringClass = classReflection(QueryBuilder::class);

    $method = (new QueryMacrosExtension)->getMethod($declaringClass, 'whereLikeEscaped');

    expect($method->getName())->toBe('whereLikeEscaped')
        ->and($method->getDeclaringClass()->getName())->toBe(QueryBuilder::class)
        ->and($method->getPrototype())->toBe($method)
        ->and($method->isPublic())->toBeTrue()
        ->and($method->isPrivate())->toBeFalse()
        ->and($method->isStatic())->toBeFalse()
        ->and($method->getDocComment())->toBeNull()
        ->and($method->getThrowType())->toBeNull()
        ->and($method->getDeprecatedDescription())->toBeNull()
        ->and($method->isDeprecated())->toEqual(TrinaryLogic::createNo())
        ->and($method->isFinal())->toEqual(TrinaryLogic::createNo())
        ->and($method->isInternal())->toEqual(TrinaryLogic::createNo())
        ->and($method->hasSideEffects())->toEqual(TrinaryLogic::createYes());

    $variant = $method->getVariants()[0];

    expect($variant->isVariadic())->toBeFalse()
        ->and($variant->getReturnType()->describe(VerbosityLevel::precise()))->toBe('static(Illuminate\Database\Query\Builder)');

    $parameters = $variant->getParameters();

    foreach ($parameters as $parameter) {
        expect($parameter->isVariadic())->toBeFalse()
            ->and($parameter->passedByReference()->equals(PassedByReference::createNo()))->toBeTrue();
    }

    expect($parameters)->toHaveCount(3)
        ->and($parameters[0]->getName())->toBe('column')
        ->and($parameters[0]->getType()->describe(VerbosityLevel::precise()))->toBe('string')
        ->and($parameters[0]->isOptional())->toBeFalse()
        ->and($parameters[1]->getName())->toBe('term')
        ->and($parameters[1]->isOptional())->toBeFalse()
        ->and($parameters[2]->getName())->toBe('boolean')
        ->and($parameters[2]->isOptional())->toBeTrue()
        ->and($parameters[2]->getDefaultValue()?->describe(VerbosityLevel::precise()))->toBe("'and'");
});

it('returns the eloquent builder itself so the model generic survives', function (): void {
    $declaringClass = classReflection(EloquentBuilder::class);

    $method = (new QueryMacrosExtension)->getMethod($declaringClass, 'whereLikeEscaped');

    expect($method->getVariants()[0]->getReturnType()->describe(VerbosityLevel::precise()))
        ->toStartWith('static(Illuminate\Database\Eloquent\Builder<TModel of Illuminate\Database\Eloquent\Model');
});
