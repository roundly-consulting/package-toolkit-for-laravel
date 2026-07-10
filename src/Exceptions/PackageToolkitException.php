<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Exceptions;

use RuntimeException;

/**
 * Base exception for every error surfaced by the package toolkit. Consumers can
 * catch this to handle any toolkit failure, or the more specific subclasses.
 */
abstract class PackageToolkitException extends RuntimeException {}
