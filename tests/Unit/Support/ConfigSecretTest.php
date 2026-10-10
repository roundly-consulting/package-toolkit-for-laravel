<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\PackageToolkit\Support\ConfigValidator;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\CustomConfigException;
use RoundlyConsulting\PackageToolkit\Tests\Support\Thrown;

describe('secret', function (): void {
    it('returns a present string as given, never trimmed', function (): void {
        config()->set('toolbox.secret', " s3cr3t\n");

        expect(Config::secret('toolbox.secret'))->toBe(" s3cr3t\n")
            ->and(Config::for(['k' => 'abc'])->secret('k'))->toBe('abc');
    });

    it('reads a key that is not set as null', function (mixed $blank): void {
        config()->set('toolbox.secret', $blank);

        expect(Config::secret('toolbox.secret'))->toBeNull()
            ->and(Config::secret('toolbox.never_set'))->toBeNull()
            ->and(Config::for(['k' => $blank])->secret('k'))->toBeNull();
    })->with(['null' => [null], 'empty' => [''], 'whitespace' => [" \t\n"]]);

    it('THROWS on a non-string, describing it by type only', function (mixed $value, string $type): void {
        config()->set('toolbox.secret', $value);

        expect(fn (): ?string => Config::secret('toolbox.secret'))->toThrow(
            InvalidConfigurationException::class,
            "Configuration value [toolbox.secret] must be a non-empty string, [{$type}] given.",
        );
    })->with([
        'an int' => [4815162342, 'int'],
        'a float' => [48.15, 'float'],
        'true' => [true, 'bool'],
        'false' => [false, 'bool'],
        'an array' => [['s3cr3t'], 'array'],
        'an object' => [new ArrayObject(['s3cr3t']), 'ArrayObject'],
    ]);
});

describe('requireSecret', function (): void {
    it('returns a present string as given', function (): void {
        config()->set('toolbox.secret', ' s3cr3t ');

        expect(Config::requireSecret('toolbox.secret'))->toBe(' s3cr3t ')
            ->and(ConfigValidator::forArray(['k' => 'abc'])->requireSecret('k'))->toBe('abc');
    });

    it('THROWS missing when the key is not set', function (mixed $blank): void {
        config()->set('toolbox.secret', $blank);

        expect(fn (): string => Config::requireSecret('toolbox.secret'))->toThrow(
            InvalidConfigurationException::class,
            'Configuration value [toolbox.secret] is required but missing.',
        );
    })->with(['null' => [null], 'empty' => [''], 'whitespace' => ['   ']]);

    it('THROWS on a non-string, describing it by type only', function (): void {
        config()->set('toolbox.secret', 4815162342);

        Config::requireSecret('toolbox.secret');
    })->throws(InvalidConfigurationException::class, 'Configuration value [toolbox.secret] must be a non-empty string, [int] given.');

    it('throws a nominated exception with the same type-only message', function (): void {
        Config::for(['k' => ['s3cr3t']], CustomConfigException::class)->requireSecret('k');
    })->throws(CustomConfigException::class, 'Configuration value [k] must be a non-empty string, [array] given.');
});

describe('secretList', function (): void {
    it('reads a config array or an env comma list, like list()', function (mixed $value): void {
        config()->set('toolbox.keys', $value);

        expect(Config::secretList('toolbox.keys'))->toBe(['key-one', 'key-two']);
    })->with([
        'an array' => [['key-one', 'key-two']],
        'a padded array' => [[' key-one ', '', 'key-two']],
        'a keyed array' => [['old' => 'key-one', 'new' => 'key-two']],
        'a comma list' => ['key-one,key-two'],
        'a padded comma list' => [' key-one , ,key-two '],
    ]);

    it('reads a key that is not set, or holds no items, as an empty list', function (mixed $value): void {
        config()->set('toolbox.keys', $value);

        expect(Config::secretList('toolbox.keys'))->toBe([])
            ->and(Config::secretList('toolbox.never_set'))->toBe([]);
    })->with(['null' => [null], 'empty' => [''], 'a lone comma' => [','], 'an empty array' => [[]], 'blank items' => [[' ', '']]]);

    it('passes every item through $each', function (): void {
        $seen = [];

        $keys = Config::for(['keys' => 'a,b'])->secretList('keys', function (string $key) use (&$seen): bool {
            $seen[] = $key;

            return true;
        });

        expect($keys)->toBe(['a', 'b'])->and($seen)->toBe(['a', 'b']);
    });

    it('THROWS on a failure, describing the value or item by type only', function (mixed $value, ?Closure $each, string $message): void {
        config()->set('toolbox.keys', $value);

        expect(fn (): array => Config::secretList('toolbox.keys', $each))->toThrow(InvalidConfigurationException::class, $message);
    })->with([
        'not a list' => [4815162342, null, 'Configuration value [toolbox.keys] must be a list of strings (an array or a comma-separated string), [int] given.'],
        'a non-string item' => [['key-one', 4815162342], null, 'Configuration value [toolbox.keys] must contain only string items, [int] given.'],
        'a rejected item' => [['key-one', 'short'], static fn (string $key): bool => strlen($key) > 5, 'Configuration value [toolbox.keys] must contain only valid items, [string] given.'],
    ]);
});

/**
 * The point of the secret readers: a misconfigured secret shows nowhere — not in the message,
 * the trace string, any frame's arguments, nor an exception chained to it.
 */
describe('a misconfigured secret', function (): void {
    it('shows nowhere', function (Closure $read, string $secret, string $frame, string $message): void {
        $thrown = Thrown::by($read);

        expect($thrown->exception->getMessage())->toBe($message)
            ->and($thrown->anywhere($secret))->toBe([])
            ->and($thrown->frame($frame)[0])->toStartWith('svc.');
    })->with([
        'secret(), an int' => [
            static fn (): ?string => Config::for(['svc.secret' => 4815162342424242])->secret('svc.secret'),
            '4815162342424242', 'secret', 'Configuration value [svc.secret] must be a non-empty string, [int] given.',
        ],
        'requireSecret(), an int' => [
            static fn (): string => Config::for(['svc.secret' => 4815162342424242])->requireSecret('svc.secret'),
            '4815162342424242', 'requireSecret', 'Configuration value [svc.secret] must be a non-empty string, [int] given.',
        ],
        'requireSecret(), an array' => [
            static fn (): string => Config::for(['svc.secret' => ['TOPSECRET-abc']])->requireSecret('svc.secret'),
            'TOPSECRET-abc', 'requireSecret', 'Configuration value [svc.secret] must be a non-empty string, [array] given.',
        ],
        'requireSecret(), a nominated exception' => [
            static fn (): string => Config::for(['svc.secret' => 4815162342424242], CustomConfigException::class)->requireSecret('svc.secret'),
            '4815162342424242', 'requireSecret', 'Configuration value [svc.secret] must be a non-empty string, [int] given.',
        ],
        'secretList(), a rejected item' => [
            static fn (): array => Config::for(['svc.keys' => ['TOPSECRET-short']])->secretList('svc.keys', static fn (string $key): bool => strlen($key) >= 32),
            'TOPSECRET-short', 'secretList', 'Configuration value [svc.keys] must contain only valid items, [string] given.',
        ],
        'secretList(), a non-string item next to a secret' => [
            static fn (): array => Config::for(['svc.keys' => ['TOPSECRET-item', 4815162342424242]])->secretList('svc.keys'),
            'TOPSECRET-item', 'secretList', 'Configuration value [svc.keys] must contain only string items, [int] given.',
        ],
        'secretList(), not a list' => [
            static fn (): array => Config::for(['svc.keys' => 4815162342424242])->secretList('svc.keys'),
            '4815162342424242', 'secretList', 'Configuration value [svc.keys] must be a list of strings (an array or a comma-separated string), [int] given.',
        ],
    ]);

    it('shows nowhere when it is read from the global repository', function (): void {
        config()->set('svc.secret', 4815162342424242);

        $thrown = Thrown::by(static fn (): string => Config::requireSecret('svc.secret'));

        expect($thrown->anywhere('4815162342424242'))->toBe([])
            ->and($thrown->frame('requireSecret'))->toBe(['svc.secret']);
    });

    it('shows nowhere in the chain when a package rethrows its own exception', function (): void {
        $thrown = Thrown::by(static function (): never {
            try {
                Config::for(['svc.secret' => ['TOPSECRET-abc']])->requireSecret('svc.secret');
            } catch (InvalidConfigurationException $e) {
                throw new CustomConfigException('The service is misconfigured.', 0, $e);
            }
        });

        expect($thrown->exception->getPrevious())->toBeInstanceOf(InvalidConfigurationException::class)
            ->and($thrown->anywhere('TOPSECRET-abc'))->toBe([]);
    });

    it('is described by type only by any factory, once wrapped in SensitiveParameterValue', function (): void {
        $exception = InvalidConfigurationException::notAString('svc.secret', new SensitiveParameterValue('TOPSECRET-abc'));

        expect($exception->getMessage())->toBe('Configuration value [svc.secret] must be a non-empty string, [string] given.')
            ->and(InvalidConfigurationException::outOfRange('svc.n', 1, 10, new SensitiveParameterValue(4815162342))->getMessage())
            ->toBe('Configuration value [svc.n] must be between 1 and 10, [int] given.');
    });
});
