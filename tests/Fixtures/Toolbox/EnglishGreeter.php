<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox;

final class EnglishGreeter implements Greeter
{
    public function greet(): string
    {
        return 'hello';
    }
}
