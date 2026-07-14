<?php

declare(strict_types=1);

use Illuminate\Database\Query\Builder as QueryBuilder;
use RoundlyConsulting\PackageToolkit\PHPStan\QueryMacrosExtension;

/**
 * The macros only exist once a provider has booted, which a static analyser never
 * does. These tests guard the two things that make them visible anyway — the stub
 * file and the reflection extension — so a refactor cannot silently drop either
 * and red every consumer's `composer analyse` on CI.
 */
$root = dirname(__DIR__, 3);

/**
 * @return list<string>
 */
function registeredMacros(string $source, string $class): array
{
    preg_match_all('/'.$class.'::macro\(\'(\w+)\'/', $source, $matches);

    return array_values(array_unique($matches[1]));
}

it('ships the stub file and the extension config', function () use ($root): void {
    expect($root.'/stubs/BlueprintMacros.stub')->toBeReadableFile()
        ->and($root.'/extension.neon')->toBeReadableFile();
});

it('wires the extension config into composer so consumers auto-load it', function () use ($root): void {
    /** @var array{extra?: array{phpstan?: array{includes?: list<string>}}} $composer */
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['extra']['phpstan']['includes'] ?? [])->toContain('extension.neon');
});

it('registers the stub file and the query-macro extension in the extension config', function () use ($root): void {
    $extension = (string) file_get_contents($root.'/extension.neon');

    expect($extension)->toContain('stubs/BlueprintMacros.stub')
        ->toContain(QueryMacrosExtension::class)
        ->toContain('phpstan.broker.methodsClassReflectionExtension');
});

it('declares every registered blueprint macro in the stub', function () use ($root): void {
    $source = (string) file_get_contents($root.'/src/Concerns/RegistersBlueprintMacros.php');
    $stub = (string) file_get_contents($root.'/stubs/BlueprintMacros.stub');

    $macros = registeredMacros($source, 'Blueprint');

    expect($macros)->not->toBeEmpty();

    foreach ($macros as $macro) {
        expect($stub)->toContain(' '.$macro.'(');
    }
});

it('declares every registered builder macro in the reflection extension', function () use ($root): void {
    $source = (string) file_get_contents($root.'/src/Concerns/RegistersBlueprintMacros.php');

    $macros = [
        ...registeredMacros($source, 'QueryBuilder'),
        ...registeredMacros($source, 'EloquentBuilder'),
    ];

    expect($macros)->not->toBeEmpty();

    $extension = new QueryMacrosExtension;
    $builder = classReflection(QueryBuilder::class);

    foreach (array_unique($macros) as $macro) {
        expect($extension->hasMethod($builder, $macro))->toBeTrue();
    }
});
