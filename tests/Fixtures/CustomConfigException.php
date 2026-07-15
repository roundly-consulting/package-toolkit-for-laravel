<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures;

use RuntimeException;

/**
 * Stands in for a package's own exception (e.g. PasskeyException) to prove the
 * config validator can be told to throw a caller-nominated class.
 */
final class CustomConfigException extends RuntimeException {}
