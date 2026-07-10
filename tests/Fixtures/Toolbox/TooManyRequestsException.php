<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox;

use RoundlyConsulting\PackageToolkit\Concerns\ProvidesRetryAfter;
use RoundlyConsulting\PackageToolkit\Contracts\HasRetryAfter;
use RuntimeException;

/**
 * Fixture exception proving {@see ProvidesRetryAfter} satisfies
 * {@see HasRetryAfter} without coupling to any base exception.
 */
final class TooManyRequestsException extends RuntimeException implements HasRetryAfter
{
    use ProvidesRetryAfter;
}
