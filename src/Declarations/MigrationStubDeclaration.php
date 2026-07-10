<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Declarations;

/**
 * A single publishable `.php.stub` migration declared on a
 * {@see \RoundlyConsulting\PackageToolkit\Package}. Its destination filename is
 * timestamped at publish time so migrations run in declaration order.
 */
final readonly class MigrationStubDeclaration
{
    public function __construct(
        public string $name,
        public string $tag,
    ) {}
}
