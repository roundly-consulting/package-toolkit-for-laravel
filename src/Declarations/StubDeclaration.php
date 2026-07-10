<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Declarations;

/**
 * A generic publishable stub (source path → destination path under a tag)
 * declared on a {@see \RoundlyConsulting\PackageToolkit\Package}.
 */
final readonly class StubDeclaration
{
    public function __construct(
        public string $from,
        public string $to,
        public string $tag,
    ) {}
}
