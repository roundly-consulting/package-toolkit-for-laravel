<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Declarations;

/**
 * A class alias declared on a {@see \RoundlyConsulting\PackageToolkit\Package},
 * optionally gated behind a boolean config key.
 */
final readonly class FacadeAliasDeclaration
{
    /**
     * @param  class-string  $class
     */
    public function __construct(
        public string $class,
        public string $alias,
        public ?string $configKey = null,
    ) {}
}
