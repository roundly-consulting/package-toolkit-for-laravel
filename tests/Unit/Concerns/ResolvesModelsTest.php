<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\ModelRegistry;
use RoundlyConsulting\PackageToolkit\Tests\Models\Counter;
use RoundlyConsulting\PackageToolkit\Tests\Models\Note;
use RoundlyConsulting\PackageToolkit\Tests\Models\PinnedNote;

it('resolves a configured subclass via the trait', function (): void {
    config()->set('toolbox.models.widget', PinnedNote::class);

    expect((new ModelRegistry)->widgetClass())->toBe(PinnedNote::class)
        ->and((new ModelRegistry)->widget())->toBeInstanceOf(PinnedNote::class);
});

it('falls back to the trait default only when the key is absent or null', function (): void {
    config()->set('toolbox.models.widget', null);

    expect((new ModelRegistry)->widgetClass())->toBe(Note::class)
        ->and((new ModelRegistry)->widget())->toBeInstanceOf(Note::class);
});

it('refuses a model that does not extend the default instead of falling back', function (): void {
    config()->set('toolbox.models.widget', Counter::class);

    (new ModelRegistry)->widgetClass();
})->throws(InvalidConfigurationException::class, 'Configuration value [toolbox.models.widget] must be a class-string of ['.Note::class.'], ['.Counter::class.'] given.');

it('passes a widened base through', function (): void {
    config()->set('toolbox.models.tenant', Counter::class);

    expect((new ModelRegistry)->tenantClass())->toBe(Counter::class);
});
