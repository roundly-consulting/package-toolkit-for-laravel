<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Support\LikeEscaper;

it('escapes like wildcards and the escape char', function (): void {
    expect(LikeEscaper::escape('50%_off'))->toBe('50\\%\\_off')
        ->and(LikeEscaper::escape('a\\b'))->toBe('a\\\\b');
});

it('leaves ordinary text untouched', function (): void {
    expect(LikeEscaper::escape('hello world'))->toBe('hello world');
});
