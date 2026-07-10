<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Declarations;

/**
 * A published/merged config file declared on a {@see \RoundlyConsulting\PackageToolkit\Package}.
 */
final readonly class ConfigFileDeclaration
{
    public function __construct(
        public string $fileName,
        public string $key,
        public string $tag,
    ) {}
}
