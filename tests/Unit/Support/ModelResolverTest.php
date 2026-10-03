<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\PackageToolkit\Tests\Models\Article;
use RoundlyConsulting\PackageToolkit\Tests\Models\Counter;
use RoundlyConsulting\PackageToolkit\Tests\Models\Note;
use RoundlyConsulting\PackageToolkit\Tests\Models\PinnedNote;

it('resolves a configured model class', function (): void {
    config()->set('toolbox.models.thing', Article::class);

    expect(ModelResolver::for('toolbox.models.thing'))->toBe(Article::class);
});

it('uses the default only when the key is absent or null', function (): void {
    config()->set('toolbox.models.thing', null);

    expect(ModelResolver::for('toolbox.models.thing', Note::class))->toBe(Note::class)
        ->and(ModelResolver::for('toolbox.models.never_set', Note::class))->toBe(Note::class);
});

it('accepts the packaged default and its subclasses', function (string $configured): void {
    config()->set('toolbox.models.note', $configured);

    expect(ModelResolver::for('toolbox.models.note', Note::class))->toBe($configured);
})->with([
    'the packaged default' => [Note::class],
    'a subclass' => [PinnedNote::class],
]);

it('builds a fresh model instance', function (): void {
    config()->set('toolbox.models.note', PinnedNote::class);

    expect(ModelResolver::newModel('toolbox.models.note', Note::class))->toBeInstanceOf(PinnedNote::class);
});

it('refuses a model that does not extend the packaged default, instead of falling back to it', function (mixed $configured, string $given): void {
    config()->set('toolbox.models.note', $configured);

    expect(fn (): string => ModelResolver::for('toolbox.models.note', Note::class))->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [toolbox.models.note] must be a class-string of ['.Note::class."], [{$given}] given.",
    );
})->with([
    'an unrelated model' => [Counter::class, Counter::class],
    'a non-model class' => [stdClass::class, 'stdClass'],
    'a missing class' => ['App\\Models\\Missing', 'App\\Models\\Missing'],
    'an empty string' => ['', "''"],
    'false' => [false, 'false'],
    'an array' => [[Note::class], 'array'],
]);

it('lets a package widen the required base when its default is only a suggestion', function (): void {
    config()->set('toolbox.models.tenant', Counter::class);

    expect(ModelResolver::for('toolbox.models.tenant', Note::class, base: Model::class))->toBe(Counter::class);
});

it('still requires an Eloquent model with no default and no base', function (mixed $configured, string $given): void {
    config()->set('toolbox.models.thing', $configured);

    expect(fn (): string => ModelResolver::for('toolbox.models.thing'))->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [toolbox.models.thing] must be a class-string of ['.Model::class."], [{$given}] given.",
    );
})->with([
    'a non-model class' => [stdClass::class, 'stdClass'],
    'a missing class' => ['NotAClass', 'NotAClass'],
    'absent, with no default' => [null, 'null'],
]);

it('refuses a non-model base even if the configured class is one', function (): void {
    config()->set('toolbox.models.thing', stdClass::class);

    ModelResolver::for('toolbox.models.thing', base: stdClass::class);
})->throws(InvalidConfigurationException::class, 'must be a class-string of [stdClass], [stdClass] given.');
