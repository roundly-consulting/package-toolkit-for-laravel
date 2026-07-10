<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\PackageToolkit\Tests\Models\Article;

beforeEach(function (): void {
    DB::table('articles')->insert([
        ['title' => 'hello world'],
        ['title' => 'he%lo there'],
        ['title' => 'completely different'],
    ]);
});

it('registers the query builder macro', function (): void {
    expect(DB::query()->getConnection())->not->toBeNull()
        ->and(Illuminate\Database\Query\Builder::hasMacro('whereLikeEscaped'))->toBeTrue();
});

it('matches a substring case-insensitively', function (): void {
    $titles = DB::table('articles')->whereLikeEscaped('title', 'HELLO')->pluck('title');

    expect($titles->all())->toBe(['hello world']);
});

it('treats user wildcards literally via the escape clause', function (): void {
    // The literal "%" must only match the row that actually contains it,
    // never behave as a wildcard that matches every row.
    $titles = DB::table('articles')->whereLikeEscaped('title', '%lo')->pluck('title');

    expect($titles->all())->toBe(['he%lo there']);
});

it('works through the eloquent builder macro', function (): void {
    $titles = Article::query()->whereLikeEscaped('title', 'different')->pluck('title');

    expect($titles->all())->toBe(['completely different']);
});

it('composes with an or boolean', function (): void {
    $count = DB::table('articles')
        ->whereLikeEscaped('title', 'hello world')
        ->whereLikeEscaped('title', 'different', 'or')
        ->count();

    expect($count)->toBe(2);
});
