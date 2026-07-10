<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Support\LockedUpdate;
use RoundlyConsulting\PackageToolkit\Tests\Models\Counter;

it('applies the mutation atomically and returns the fresh model', function (): void {
    $counter = Counter::create(['value' => 1]);

    $result = LockedUpdate::run($counter, function (Counter $locked): void {
        $locked->value = $locked->value + 9;
    });

    expect($result)->toBeInstanceOf(Counter::class)
        ->and($result->value)->toBe(10)
        ->and(Counter::query()->find($counter->id)->value)->toBe(10);
});

it('re-reads committed state before mutating', function (): void {
    $counter = Counter::create(['value' => 5]);

    // Simulate a concurrent write landing after we loaded the stale instance.
    Counter::query()->whereKey($counter->id)->update(['value' => 100]);

    $result = LockedUpdate::run($counter, function (Counter $locked): void {
        $locked->value = $locked->value + 1;
    });

    expect($result->value)->toBe(101);
});
