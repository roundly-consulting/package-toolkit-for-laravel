<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Declarations;

/**
 * A route file declared on a {@see \RoundlyConsulting\PackageToolkit\Package},
 * optionally gated behind a boolean config key.
 */
final readonly class RouteDeclaration
{
    public function __construct(
        public string $file,
        public string $tag,
        public ?string $enabledVia = null,
    ) {}
}
