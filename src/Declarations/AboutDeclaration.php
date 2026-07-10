<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Declarations;

use Closure;

/**
 * An `about` command contribution declared on a
 * {@see \RoundlyConsulting\PackageToolkit\Package}.
 */
final readonly class AboutDeclaration
{
    /**
     * @param  Closure(): array<string, string>  $data
     */
    public function __construct(
        public string $section,
        public Closure $data,
    ) {}
}
