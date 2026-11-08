<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures;

use RoundlyConsulting\PackageToolkit\Exceptions\Concerns\DescribesConfigurationErrors;
use RuntimeException;

/**
 * A package's own configuration exception that borrows the toolkit's message vocabulary
 * instead of re-implementing it — what events, notifications, tags and workflows hand-wrote.
 */
final class PackageConfigurationException extends RuntimeException
{
    use DescribesConfigurationErrors;
}
