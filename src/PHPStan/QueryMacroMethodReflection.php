<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\PHPStan;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\StaticType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;

/**
 * The static signature of the toolkit's `whereLikeEscaped()` query macro:
 * `whereLikeEscaped(string $column, string $term, string $boolean = 'and'): $this`.
 *
 * Returns `static`, so an Eloquent builder keeps its model generic and a query
 * builder stays chainable.
 */
final class QueryMacroMethodReflection implements MethodReflection
{
    public function __construct(
        private readonly ClassReflection $declaringClass,
        private readonly string $name,
    ) {}

    public function getDeclaringClass(): ClassReflection
    {
        return $this->declaringClass;
    }

    public function isStatic(): bool
    {
        return false;
    }

    public function isPrivate(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function getDocComment(): ?string
    {
        return null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrototype(): ClassMemberReflection
    {
        return $this;
    }

    /** @return list<ParametersAcceptor> */
    public function getVariants(): array
    {
        return [
            new FunctionVariant(
                TemplateTypeMap::createEmpty(),
                null,
                [
                    new QueryMacroParameter('column', new StringType, optional: false),
                    new QueryMacroParameter('term', new StringType, optional: false),
                    new QueryMacroParameter('boolean', new StringType, optional: true, defaultValue: new ConstantStringType('and')),
                ],
                false,
                new StaticType($this->declaringClass),
            ),
        ];
    }

    public function isDeprecated(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getDeprecatedDescription(): ?string
    {
        return null;
    }

    public function isFinal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function isInternal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getThrowType(): ?Type
    {
        return null;
    }

    public function hasSideEffects(): TrinaryLogic
    {
        return TrinaryLogic::createYes();
    }
}
