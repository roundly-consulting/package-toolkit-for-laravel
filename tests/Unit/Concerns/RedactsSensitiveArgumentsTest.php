<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Vault\AliasVault;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Vault\LabelVault;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Vault\SharedVault;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Vault\StockVault;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Vault\Vault;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Vault\VaultFake;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Vault\VaultManager;
use RoundlyConsulting\PackageToolkit\Tests\Support\Thrown;

const VAULT_SECRET = 'TOPSECRET-0001';
const VAULT_SECRET_2 = 'TOPSECRET-0002';
const VAULT_LABEL = 'label-visible';

/**
 * PHP redacts a `#[SensitiveParameter]` argument only in the frame of the function that
 * declares it, and a call through a facade passes `__callStatic($method, $args)` first. These
 * read that frame — the one the trait owns — with frame arguments switched on.
 */
describe('the facade frame', function (): void {
    it('carries the raw secret on a stock facade — the leak the trait closes', function (): void {
        $thrown = Thrown::by(static fn (): bool => StockVault::check(VAULT_SECRET, VAULT_LABEL));

        expect($thrown->frame('__callStatic'))->toBe(['check', [VAULT_SECRET, VAULT_LABEL]])
            ->and($thrown->inTrace(VAULT_SECRET))->not->toBe([]);
    });

    it('hides a positional secret and keeps the harmless argument visible', function (): void {
        $thrown = Thrown::by(static fn (): bool => Vault::check(VAULT_SECRET, VAULT_LABEL));
        [$method, $args] = $thrown->frame('__callStatic');

        expect($method)->toBe('check')
            ->and($args[0])->toBeInstanceOf(SensitiveParameterValue::class)
            ->and($args[1])->toBe(VAULT_LABEL)
            ->and($thrown->exception->getMessage())->toBe('The [label-visible] check failed.')
            ->and($thrown->anywhere(VAULT_SECRET))->toBe([]);
    });

    it('hides a secret passed by name, in any order', function (): void {
        $thrown = Thrown::by(static fn (): bool => Vault::check(label: VAULT_LABEL, secret: VAULT_SECRET));
        [, $args] = $thrown->frame('__callStatic');

        expect(array_keys($args))->toBe(['label', 'secret'])
            ->and($args['label'])->toBe(VAULT_LABEL)
            ->and($args['secret'])->toBeInstanceOf(SensitiveParameterValue::class)
            ->and($thrown->anywhere(VAULT_SECRET))->toBe([]);
    });

    it('hides a positional secret followed by a named argument', function (): void {
        $thrown = Thrown::by(static fn (): bool => Vault::check(VAULT_SECRET, label: VAULT_LABEL));
        [, $args] = $thrown->frame('__callStatic');

        expect($args[0])->toBeInstanceOf(SensitiveParameterValue::class)
            ->and($args['label'])->toBe(VAULT_LABEL)
            ->and($thrown->anywhere(VAULT_SECRET))->toBe([]);
    });

    it('hides every argument a sensitive variadic collects, positional or named', function (): void {
        $thrown = Thrown::by(static fn (): string => Vault::seal(VAULT_LABEL, VAULT_SECRET, VAULT_SECRET_2));
        [, $args] = $thrown->frame('__callStatic');

        expect($args[0])->toBe(VAULT_LABEL)
            ->and($args[1])->toBeInstanceOf(SensitiveParameterValue::class)
            ->and($args[2])->toBeInstanceOf(SensitiveParameterValue::class)
            ->and($thrown->anywhere('TOPSECRET'))->toBe([]);

        $thrown = Thrown::by(static fn (): string => Vault::seal(label: VAULT_LABEL, extra: VAULT_SECRET));
        [, $args] = $thrown->frame('__callStatic');

        expect($args['label'])->toBe(VAULT_LABEL)
            ->and($args['extra'])->toBeInstanceOf(SensitiveParameterValue::class)
            ->and($thrown->anywhere(VAULT_SECRET))->toBe([]);
    });

    it('keeps the arguments a harmless variadic collects visible', function (): void {
        $thrown = Thrown::by(static fn (): string => Vault::tag(VAULT_SECRET, 'first', extra: VAULT_LABEL));
        [, $args] = $thrown->frame('__callStatic');

        expect($args[0])->toBeInstanceOf(SensitiveParameterValue::class)
            ->and($args[1])->toBe('first')
            ->and($args['extra'])->toBe(VAULT_LABEL)
            ->and($thrown->anywhere(VAULT_SECRET))->toBe([]);
    });

    it('hides a misspelt named argument, which the call refuses anyway', function (): void {
        $thrown = Thrown::by(static fn (): bool => Vault::check(secrett: VAULT_SECRET, label: VAULT_LABEL));
        [, $args] = $thrown->frame('__callStatic');

        expect($thrown->exception)->toBeInstanceOf(Error::class)
            ->and($thrown->exception->getMessage())->toBe('Unknown named parameter $secrett')
            ->and($args['secrett'])->toBeInstanceOf(SensitiveParameterValue::class)
            ->and($args['label'])->toBe(VAULT_LABEL)
            ->and($thrown->anywhere(VAULT_SECRET))->toBe([]);
    });

    it('leaves the arguments of a method without a secret untouched', function (): void {
        $thrown = Thrown::by(static fn (): string => Vault::describe(VAULT_LABEL));

        expect($thrown->frame('__callStatic'))->toBe(['describe', [VAULT_LABEL]]);
    });

    it('hides the secret when the root fails to resolve', function (): void {
        app()->bind(VaultManager::class, static fn (): never => throw new RuntimeException('The vault is sealed.'));
        Vault::clearResolvedInstance();

        $thrown = Thrown::by(static fn (): bool => Vault::check(VAULT_SECRET, VAULT_LABEL));
        [, $args] = $thrown->frame('__callStatic');

        expect($thrown->exception->getMessage())->toBe('The vault is sealed.')
            ->and($args[0])->toBeInstanceOf(SensitiveParameterValue::class)
            ->and($args[1])->toBe(VAULT_LABEL)
            ->and($thrown->anywhere(VAULT_SECRET))->toBe([]);
    });

    it('hides the secret when no facade root is set', function (): void {
        $app = Facade::getFacadeApplication();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);

        try {
            $thrown = Thrown::by(static fn (): bool => Vault::check(VAULT_SECRET, VAULT_LABEL));
        } finally {
            Facade::setFacadeApplication($app);
        }

        expect($thrown->exception)->toBeInstanceOf(RuntimeException::class)
            ->and($thrown->exception->getMessage())->toBe('A facade root has not been set.')
            ->and($thrown->anywhere(VAULT_SECRET))->toBe([]);
    });

    it('keeps each facade class to its own root, even a subclass of an open facade', function (): void {
        $shared = Thrown::by(static fn (): bool => SharedVault::check(VAULT_SECRET, VAULT_LABEL));
        $label = Thrown::by(static fn (): bool => LabelVault::check(VAULT_LABEL, VAULT_SECRET));

        expect($shared->frame('__callStatic')[1][0])->toBeInstanceOf(SensitiveParameterValue::class)
            ->and($label->frame('__callStatic')[1][0])->toBe(VAULT_LABEL)
            ->and($label->frame('__callStatic')[1][1])->toBeInstanceOf(SensitiveParameterValue::class)
            ->and($label->anywhere(VAULT_SECRET))->toBe([]);
    });
});

describe('forwarding', function (): void {
    it('returns what the root returns, with the real arguments', function (): void {
        expect(Vault::verify(VAULT_SECRET, VAULT_SECRET))->toBeTrue()
            ->and(Vault::verify(VAULT_SECRET, VAULT_LABEL))->toBeFalse()
            ->and(Vault::verify(label: VAULT_SECRET, secret: VAULT_SECRET))->toBeTrue()
            ->and(Vault::encode('abc'))->toBe('616263');
    });

    it('coerces scalars for a strict caller exactly as a stock facade does', function (): void {
        // This file is strict: through DI the same call is a TypeError, so the facade calls
        // below prove the trait forwards from a non-strict file, as Facade::__callStatic does.
        expect(fn (): string => app(VaultManager::class)->encode(123))->toThrow(TypeError::class);

        expect(Vault::encode(123))->toBe(StockVault::encode(123))->toBe('313233')
            ->and(Vault::encode(bytes: 4.5))->toBe(StockVault::encode(4.5))
            ->and(fn (): string => Vault::encode([]))->toThrow(TypeError::class)
            ->and(fn (): string => StockVault::encode([]))->toThrow(TypeError::class);
    });

    it('hands a swapped fake the real arguments, and still hides them in the facade frame', function (): void {
        $fake = new VaultFake;
        Vault::swap($fake);

        $thrown = Thrown::by(static fn (): bool => Vault::check(VAULT_SECRET, VAULT_LABEL));

        expect(Vault::verify(VAULT_SECRET, VAULT_LABEL))->toBeTrue()
            ->and($fake->calls)->toBe([
                ['check', VAULT_SECRET, VAULT_LABEL],
                ['verify', VAULT_SECRET, VAULT_LABEL],
            ])
            ->and($thrown->frame('__callStatic')[1][0])->toBeInstanceOf(SensitiveParameterValue::class)
            ->and($thrown->frame('__callStatic')[1][1])->toBe(VAULT_LABEL);
    });

    it('lets shouldReceive() see the real arguments', function (): void {
        Vault::shouldReceive('verify')->once()->with(VAULT_SECRET, VAULT_LABEL)->andReturn(true);

        expect(Vault::verify(VAULT_SECRET, VAULT_LABEL))->toBeTrue();
    });

    it('forwards a macro the root declares no method for', function (): void {
        VaultManager::macro('shout', static fn (string $word): string => strtoupper($word));

        try {
            expect(Vault::shout('quiet'))->toBe('QUIET');
        } finally {
            VaultManager::flushMacros();
        }
    });

    it('forwards through an accessor that is not a class, like a stock facade', function (): void {
        app()->instance('toolbox.vault', new VaultManager);

        $thrown = Thrown::by(static fn (): bool => AliasVault::check(VAULT_SECRET, VAULT_LABEL));

        expect(AliasVault::encode('abc'))->toBe('616263')
            ->and($thrown->frame('__callStatic'))->toBe(['check', [VAULT_SECRET, VAULT_LABEL]]);
    });

    it('keeps the facade documenting its root', function (): void {
        expect(Vault::class)->toDocumentItsRoot();
    });
});
