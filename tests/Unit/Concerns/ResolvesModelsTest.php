<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\ModelRegistry;
use RoundlyConsulting\PackageToolkit\Tests\Models\Article;
use RoundlyConsulting\PackageToolkit\Tests\Models\Counter;

it('resolves a configured model class via the trait', function (): void {
    config()->set('toolbox.models.widget', Counter::class);

    expect((new ModelRegistry)->widgetClass())->toBe(Counter::class);
});

it('falls back to the trait default', function (): void {
    config()->set('toolbox.models.widget', null);

    expect((new ModelRegistry)->widgetClass())->toBe(Article::class)
        ->and((new ModelRegistry)->widget())->toBeInstanceOf(Article::class);
});
