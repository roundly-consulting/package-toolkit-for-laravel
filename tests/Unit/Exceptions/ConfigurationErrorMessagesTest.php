<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\Concerns\DescribesConfigurationErrors;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\PackageConfigurationException;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Priority;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Version;

/**
 * The golden copy of every configuration message the toolkit throws, captured from 2.0.0 before
 * the factories moved into {@see DescribesConfigurationErrors}. Packages match on these strings
 * (tests, log alerts, docs), so the move must be invisible: every entry is byte-identical, for
 * the toolkit's own exception and for a package exception that uses the trait.
 *
 * Each factory appears once per branch of the value description: a string, `''`, an int, a
 * float, a bool, null, an array, an object, and a value behind SensitiveParameterValue.
 *
 * @return array<string, array{string, list<mixed>, string}>
 */
function goldenConfigurationMessages(): array
{
    return [
        'missing' => ['missing', ['pkg.key'], 'Configuration value [pkg.key] is required but missing.'],
        'notAString int' => ['notAString', ['pkg.key', 42], 'Configuration value [pkg.key] must be a non-empty string, [42] given.'],
        'notAString empty' => ['notAString', ['pkg.key', ''], "Configuration value [pkg.key] must be a non-empty string, [''] given."],
        'notAString sensitive' => ['notAString', ['pkg.key', new SensitiveParameterValue('s3cret')], 'Configuration value [pkg.key] must be a non-empty string, [string] given.'],
        'notABoolean' => ['notABoolean', ['pkg.key', 'disabled'], 'Configuration value [pkg.key] must be a boolean (true/false, 1/0, on/off or yes/no), [disabled] given.'],
        'notAKeyType' => ['notAKeyType', ['pkg.key', 'uiid'], 'Configuration value [pkg.key] must be one of [bigint, uuid, ulid] (case-insensitive), [uiid] given.'],
        'notAnImplementation string' => ['notAnImplementation', ['pkg.key', Countable::class, 'App\\Thing'], 'Configuration value [pkg.key] must be a class-string of [Countable], [App\\Thing] given.'],
        'notAnImplementation array' => ['notAnImplementation', ['pkg.key', Countable::class, ['a']], 'Configuration value [pkg.key] must be a class-string of [Countable], [array] given.'],
        'notAnInteger float' => ['notAnInteger', ['pkg.key', 5.0], 'Configuration value [pkg.key] must be an integer, [5.0] given.'],
        'notAnInteger bool' => ['notAnInteger', ['pkg.key', true], 'Configuration value [pkg.key] must be an integer, [true] given.'],
        'notAnInteger null' => ['notAnInteger', ['pkg.key', null], 'Configuration value [pkg.key] must be an integer, [null] given.'],
        'notAFloat' => ['notAFloat', ['pkg.key', 'abc'], 'Configuration value [pkg.key] must be a decimal number, [abc] given.'],
        'notAFloat INF' => ['notAFloat', ['pkg.key', INF], 'Configuration value [pkg.key] must be a decimal number, [INF] given.'],
        'outOfRange between' => ['outOfRange', ['pkg.key', 1, 10, 11], 'Configuration value [pkg.key] must be between 1 and 10, [11] given.'],
        'outOfRange at least' => ['outOfRange', ['pkg.key', 1, null, 0], 'Configuration value [pkg.key] must be at least 1, [0] given.'],
        'outOfRange at most' => ['outOfRange', ['pkg.key', null, 10, '11'], 'Configuration value [pkg.key] must be at most 10, [11] given.'],
        'outOfRange decimal' => ['outOfRange', ['pkg.key', 0.5, 1.5, 2.25], 'Configuration value [pkg.key] must be between 0.5 and 1.5, [2.25] given.'],
        'notAList' => ['notAList', ['pkg.key', 42], 'Configuration value [pkg.key] must be a list of strings (an array or a comma-separated string), [42] given.'],
        'notAList sensitive' => ['notAList', ['pkg.key', new SensitiveParameterValue(false)], 'Configuration value [pkg.key] must be a list of strings (an array or a comma-separated string), [bool] given.'],
        'notAStringItem int' => ['notAStringItem', ['pkg.key', 7], 'Configuration value [pkg.key] must contain only string items, [7] given.'],
        'notAStringItem object' => ['notAStringItem', ['pkg.key', new stdClass], 'Configuration value [pkg.key] must contain only string items, [stdClass] given.'],
        'notAValidListItem' => ['notAValidListItem', ['pkg.key', 'fr'], 'Configuration value [pkg.key] must contain only valid items, [fr] given.'],
        'notAValidListItem sensitive' => ['notAValidListItem', ['pkg.key', new SensitiveParameterValue('k1')], 'Configuration value [pkg.key] must contain only valid items, [string] given.'],
        'notAValidEnum int' => ['notAValidEnum', ['pkg.key', Priority::class, 'urgent'], 'Configuration value [pkg.key] must be one of [1, 2, -1], [urgent] given.'],
        'notAValidEnum string' => ['notAValidEnum', ['pkg.key', Version::class, '9'], 'Configuration value [pkg.key] must be one of [1, 2], [9] given.'],
        'notOneOf' => ['notOneOf', ['pkg.key', ['a', 'b'], 'c'], 'Configuration value [pkg.key] must be one of [a, b], [c] given.'],
        'notOneOf keyed' => ['notOneOf', ['pkg.key', ['x' => 'a'], false], 'Configuration value [pkg.key] must be one of [a], [false] given.'],
        'notAModel' => ['notAModel', ['pkg.key', 'App\\User'], 'Configuration value [pkg.key] must be a class-string of [Illuminate\\Database\\Eloquent\\Model], [App\\User] given.'],
        'notAModel custom base' => ['notAModel', ['pkg.key', 'App\\User', Countable::class], 'Configuration value [pkg.key] must be a class-string of [Countable], [App\\User] given.'],
    ];
}

/**
 * The factories 2.1 added for the new readers, pinned the same way.
 *
 * @return array<string, array{string, list<mixed>, string}>
 */
function addedConfigurationMessages(): array
{
    return [
        'notAnIntegerList' => ['notAnIntegerList', ['pkg.key', true], 'Configuration value [pkg.key] must be a list of integers (an array or a comma-separated string), [true] given.'],
        'notAnIntegerItem' => ['notAnIntegerItem', ['pkg.key', 'five'], 'Configuration value [pkg.key] must contain only integer items, [five] given.'],
        'itemOutOfRange between' => ['itemOutOfRange', ['pkg.key', 1, 7, 9], 'Configuration value [pkg.key] must contain only items between 1 and 7, [9] given.'],
        'itemOutOfRange at least' => ['itemOutOfRange', ['pkg.key', 1, null, 0], 'Configuration value [pkg.key] must contain only items at least 1, [0] given.'],
        'itemOutOfRange at most' => ['itemOutOfRange', ['pkg.key', null, 7, '8'], 'Configuration value [pkg.key] must contain only items at most 7, [8] given.'],
        'notAnIntegerOrOff' => ['notAnIntegerOrOff', ['pkg.key', ['off', 'none'], 'never'], 'Configuration value [pkg.key] must be an integer or one of [off, none], [never] given.'],
        'notAStringMap' => ['notAStringMap', ['pkg.key', 'view'], 'Configuration value [pkg.key] must be a map of names to non-empty strings, [view] given.'],
        'notAStringMap int key' => ['notAStringMap', ['pkg.key', 0], 'Configuration value [pkg.key] must be a map of names to non-empty strings, [0] given.'],
    ];
}

it('keeps every 2.0 message byte-identical on the toolkit exception', function (string $factory, array $arguments, string $message): void {
    $exception = InvalidConfigurationException::{$factory}(...$arguments);

    expect($exception)->toBeInstanceOf(InvalidConfigurationException::class)
        ->and($exception->getMessage())->toBe($message);
})->with(goldenConfigurationMessages());

it('keeps the database driver message on the toolkit exception', function (): void {
    expect(InvalidConfigurationException::unsupportedDatabaseDriver('sqlsrv')->getMessage())
        ->toBe('Unsupported database driver [sqlsrv].');
});

it('gives a package exception the same messages, typed as the package exception', function (string $factory, array $arguments, string $message): void {
    $exception = PackageConfigurationException::{$factory}(...$arguments);

    expect($exception)->toBeInstanceOf(PackageConfigurationException::class)
        ->and($exception->getMessage())->toBe($message);
})->with([...goldenConfigurationMessages(), ...addedConfigurationMessages()]);

it('pins the messages of the factories the new readers throw', function (string $factory, array $arguments, string $message): void {
    expect(InvalidConfigurationException::{$factory}(...$arguments)->getMessage())->toBe($message);
})->with(addedConfigurationMessages());

it('keeps the toolkit exception final and closed to subclassing', function (): void {
    $class = new ReflectionClass(InvalidConfigurationException::class);

    expect($class->isFinal())->toBeTrue()
        ->and($class->getTraitNames())->toBe([DescribesConfigurationErrors::class]);
});

it('keeps a sensitive value out of the stack frames of every factory', function (): void {
    $checked = 0;

    foreach ((new ReflectionClass(PackageConfigurationException::class))->getMethods(ReflectionMethod::IS_STATIC) as $method) {
        foreach ($method->getParameters() as $parameter) {
            if (in_array($parameter->getName(), ['value', 'item'], true)) {
                expect($parameter->getAttributes(SensitiveParameter::class))
                    ->not->toBeEmpty("{$method->getName()}(\${$parameter->getName()}) is not #[SensitiveParameter]");
                $checked++;
            }
        }
    }

    expect($checked)->toBeGreaterThan(15);
});

it('describes a value by its type when it is neither a scalar nor a string', function (): void {
    expect(PackageConfigurationException::notAString('pkg.key', null)->getMessage())
        ->toBe('Configuration value [pkg.key] must be a non-empty string, [null] given.')
        ->and(PackageConfigurationException::notAString('pkg.key', 1.5)->getMessage())
        ->toBe('Configuration value [pkg.key] must be a non-empty string, [1.5] given.');
});
