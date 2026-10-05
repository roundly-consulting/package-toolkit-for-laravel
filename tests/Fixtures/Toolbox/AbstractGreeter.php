<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox;

/**
 * A {@see Greeter} that cannot be instantiated: config naming it must be refused as a
 * configuration error, like any other value that is not a usable implementation.
 */
abstract class AbstractGreeter implements Greeter {}
