<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox;

use Illuminate\Console\Command;

final class ToolboxCommand extends Command
{
    protected $signature = 'toolbox:demo';

    protected $description = 'Fixture command registered through the toolkit builder.';

    public function handle(): int
    {
        $this->info('toolbox ran');

        return self::SUCCESS;
    }
}
