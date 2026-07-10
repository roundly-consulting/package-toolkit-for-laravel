<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Contracts\HasRetryAfter;
use RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\TooManyRequestsException;

it('defaults to zero seconds', function (): void {
    expect((new TooManyRequestsException)->retryAfterSeconds())->toBe(0);
});

it('carries and exposes a retry delay', function (): void {
    $exception = (new TooManyRequestsException)->withRetryAfter(30);

    expect($exception)->toBeInstanceOf(HasRetryAfter::class)
        ->and($exception->retryAfterSeconds())->toBe(30);
});

it('clamps a negative delay to zero', function (): void {
    expect((new TooManyRequestsException)->withRetryAfter(-5)->retryAfterSeconds())->toBe(0);
});
