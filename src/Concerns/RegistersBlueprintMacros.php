<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Concerns;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Support\BlueprintMacros;
use RoundlyConsulting\PackageToolkit\Support\LikeSearch;

/**
 * Registers the toolkit's schema and query-builder macros: key-type aware
 * foreign/morph columns, the `auditable()`/`polymorphicSubject()` conventions,
 * and the `whereLikeEscaped()` portable search helper. Registration is guarded
 * against double-boot via the framework's own `hasMacro()` check, so a package
 * discovered twice never re-registers a macro.
 */
trait RegistersBlueprintMacros
{
    protected function registerBlueprintMacros(): void
    {
        $this->registerKeyTypeMacros();
        $this->registerConventionMacros();
        $this->registerLikeEscapedMacro();
    }

    private function registerKeyTypeMacros(): void
    {
        if (! Blueprint::hasMacro('ownerKey')) {
            Blueprint::macro('ownerKey', function (string $name, KeyType $type, bool $nullable = false, bool $index = true): ColumnDefinition {
                /** @var Blueprint $this */
                return BlueprintMacros::ownerKey($this, $name, $type, $nullable, $index);
            });
        }

        if (! Blueprint::hasMacro('morphKey')) {
            Blueprint::macro('morphKey', function (string $name, KeyType $type, bool $nullable = false): void {
                /** @var Blueprint $this */
                BlueprintMacros::morphKey($this, $name, $type, $nullable);
            });
        }
    }

    private function registerConventionMacros(): void
    {
        if (! Blueprint::hasMacro('auditable')) {
            Blueprint::macro('auditable', function (): void {
                /** @var Blueprint $this */
                BlueprintMacros::auditable($this);
            });
        }

        if (! Blueprint::hasMacro('polymorphicSubject')) {
            Blueprint::macro('polymorphicSubject', function (string $name, KeyType $type, bool $nullable = false): void {
                /** @var Blueprint $this */
                BlueprintMacros::polymorphicSubject($this, $name, $type, $nullable);
            });
        }
    }

    private function registerLikeEscapedMacro(): void
    {
        if (! QueryBuilder::hasMacro('whereLikeEscaped')) {
            QueryBuilder::macro('whereLikeEscaped', function (string $column, string $term, string $boolean = 'and'): QueryBuilder {
                /** @var QueryBuilder $this */
                return LikeSearch::apply($this, $column, $term, $boolean);
            });
        }

        if (! EloquentBuilder::hasGlobalMacro('whereLikeEscaped')) {
            EloquentBuilder::macro('whereLikeEscaped', function (string $column, string $term, string $boolean = 'and'): EloquentBuilder {
                /** @var EloquentBuilder<\Illuminate\Database\Eloquent\Model> $this */
                LikeSearch::apply($this->getQuery(), $column, $term, $boolean);

                return $this;
            });
        }
    }
}
