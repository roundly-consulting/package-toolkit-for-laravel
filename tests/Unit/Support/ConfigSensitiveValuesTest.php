<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\CustomConfigException;
use RoundlyConsulting\PackageToolkit\Tests\Support\Thrown;

/**
 * A misconfigured value reaches more places than the exception message: every stack frame
 * records its arguments (unless production's `zend.exception_ignore_args` strips them), and
 * error trackers, `print_r($e)` and custom trace loggers serialise those frames. The readers
 * keep their messages as they are — a non-secret value named in the message is the point —
 * but no frame may carry the value.
 */
describe('reader values in stack frames', function (): void {
    it('keeps the value out of every frame, though the message still names it', function (Closure $read, string $value, string $message): void {
        $thrown = Thrown::by($read);

        expect($thrown->exception)->toBeInstanceOf(InvalidConfigurationException::class)
            ->and($thrown->exception->getMessage())->toBe($message)
            ->and($thrown->inTrace($value))->toBe([]);
    })->with([
        'requireString(), an int' => [
            static fn (): string => Config::for(['svc.key' => 4815162342424242])->requireString('svc.key'),
            '4815162342424242',
            'Configuration value [svc.key] must be a non-empty string, [4815162342424242] given.',
        ],
        'string(), an int' => [
            static fn (): string => Config::for(['svc.key' => 4815162342424242])->string('svc.key', 'x'),
            '4815162342424242',
            'Configuration value [svc.key] must be a non-empty string, [4815162342424242] given.',
        ],
        'string(), an array' => [
            static fn (): string => Config::for(['svc.key' => ['TOPSECRET-in-array']])->string('svc.key', 'x'),
            'TOPSECRET-in-array',
            'Configuration value [svc.key] must be a non-empty string, [array] given.',
        ],
        'integer(), out of range' => [
            static fn (): int => Config::for(['svc.n' => 4815162342424242])->integer('svc.n', 1, max: 10),
            '4815162342424242',
            'Configuration value [svc.n] must be at most 10, [4815162342424242] given.',
        ],
        'integer(), a default out of range' => [
            static fn (): int => Config::for([])->integer('svc.n', 4815162342424242, max: 10),
            '4815162342424242',
            'Configuration value [svc.n] must be at most 10, [4815162342424242] given.',
        ],
        'float(), not a number' => [
            static fn (): float => Config::for(['svc.f' => ['TOPSECRET-in-array']])->float('svc.f', 1.0),
            'TOPSECRET-in-array',
            'Configuration value [svc.f] must be a decimal number, [array] given.',
        ],
        'list(), a rejected item' => [
            static fn (): array => Config::for(['svc.keys' => ['TOPSECRET-short']])->list('svc.keys', [], static fn (string $key): bool => strlen($key) >= 32),
            'TOPSECRET-short',
            'Configuration value [svc.keys] must contain only valid items, [TOPSECRET-short] given.',
        ],
        'list(), a non-string item next to a secret' => [
            static fn (): array => Config::for(['svc.keys' => ['TOPSECRET-item', 42]])->list('svc.keys', []),
            'TOPSECRET-item',
            'Configuration value [svc.keys] must contain only string items, [42] given.',
        ],
        'list(), not a list' => [
            static fn (): array => Config::for(['svc.keys' => 4815162342424242])->list('svc.keys', []),
            '4815162342424242',
            'Configuration value [svc.keys] must be a list of strings (an array or a comma-separated string), [4815162342424242] given.',
        ],
        'enum(), no such case' => [
            static fn (): mixed => Config::for(['svc.e' => 'TOPSECRET-case'])->enum('svc.e', KeyType::class),
            'TOPSECRET-case',
            'Configuration value [svc.e] must be one of [bigint, uuid, ulid], [TOPSECRET-case] given.',
        ],
        'oneOf(), outside the vocabulary' => [
            static fn (): string => Config::for(['svc.o' => 'TOPSECRET-word'])->oneOf('svc.o', ['a', 'b'], 'a'),
            'TOPSECRET-word',
            'Configuration value [svc.o] must be one of [a, b], [TOPSECRET-word] given.',
        ],
        'boolean(), not a boolean' => [
            static fn (): bool => Config::for(['svc.b' => 'TOPSECRET-flag'])->boolean('svc.b'),
            'TOPSECRET-flag',
            'Configuration value [svc.b] must be a boolean (true/false, 1/0, on/off or yes/no), [TOPSECRET-flag] given.',
        ],
    ]);

    it('keeps it out of the frames of a nominated exception too', function (): void {
        $thrown = Thrown::by(static fn (): string => Config::for(['svc.key' => 4815162342424242], CustomConfigException::class)->requireString('svc.key'));

        expect($thrown->exception)->toBeInstanceOf(CustomConfigException::class)
            ->and($thrown->inTrace('4815162342424242'))->toBe([])
            ->and($thrown->frame('requireString'))->toBe(['svc.key']);
    });

    it('keeps it out of the frames when it reads the global repository', function (): void {
        config()->set('toolbox.secret', 4815162342424242);

        $thrown = Thrown::by(static fn (): string => Config::requireString('toolbox.secret'));

        expect($thrown->inTrace('4815162342424242'))->toBe([])
            ->and($thrown->frame('requireString'))->toBe(['toolbox.secret']);
    });

    it('records frames with arguments, so "absent" means something', function (): void {
        $thrown = Thrown::by(static fn (): string => Config::for(['svc.key' => 5])->requireString('svc.key'));

        expect($thrown->frame('requireString'))->toBe(['svc.key'])
            ->and($thrown->frame('notAString')[0])->toBe('svc.key');
    });
});
