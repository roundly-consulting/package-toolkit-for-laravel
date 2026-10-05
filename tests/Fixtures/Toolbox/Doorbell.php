<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox;

/**
 * A concrete-class contract that a package binds with itself as the default, so a host
 * can swap in a subclass. Its dependency proves the default is still autowired.
 */
class Doorbell
{
    public function __construct(public readonly EnglishGreeter $greeter) {}
}
