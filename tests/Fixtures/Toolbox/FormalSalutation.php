<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox;

final class FormalSalutation extends Salutation
{
    public function salute(): string
    {
        return 'good day';
    }
}
