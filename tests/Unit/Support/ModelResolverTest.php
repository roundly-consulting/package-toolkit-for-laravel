<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\PackageToolkit\Tests\Models\Article;
use RoundlyConsulting\PackageToolkit\Tests\Models\Counter;

it('resolves a configured model class', function (): void {
    config()->set('toolbox.models.thing', Article::class);

    expect(ModelResolver::for('toolbox.models.thing'))->toBe(Article::class);
});

it('falls back to the default class', function (): void {
    config()->set('toolbox.models.thing', null);

    expect(ModelResolver::for('toolbox.models.thing', Counter::class))->toBe(Counter::class);
});

it('builds a fresh model instance', function (): void {
    config()->set('toolbox.models.thing', Article::class);

    expect(ModelResolver::newModel('toolbox.models.thing'))->toBeInstanceOf(Article::class);
});

it('throws when the value is not a model class', function (): void {
    config()->set('toolbox.models.thing', 'NotAClass');

    ModelResolver::for('toolbox.models.thing');
})->throws(InvalidConfigurationException::class, 'Eloquent model');

it('throws when the value is a non-model class', function (): void {
    config()->set('toolbox.models.thing', stdClass::class);

    ModelResolver::for('toolbox.models.thing');
})->throws(InvalidConfigurationException::class);
