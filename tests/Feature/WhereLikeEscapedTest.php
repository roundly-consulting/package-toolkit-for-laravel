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

/**
 * A query builder on a connection whose driver the DatabaseDriver enum does not model. Only
 * the compiled SQL and bindings are inspected, so the PDO underneath never runs a statement.
 */
function queryOnDriver(string $driver): Illuminate\Database\Query\Builder
{
    $connection = new Illuminate\Database\SqlServerConnection(new PDO('sqlite::memory:'), '', '', ['driver' => $driver, 'name' => $driver]);

    return $connection->query()->from('articles');
}

it('searches on a Laravel driver the DatabaseDriver enum does not model instead of throwing', function (): void {
    $query = queryOnDriver('sqlsrv')->whereLikeEscaped('title', '50%_off');

    expect($query->toSql())->toBe('select * from [articles] where [title] like ? escape ?')
        ->and($query->getBindings())->toBe(['%50\\%\\_off%', '\\']);
});

it('keeps a user bracket literal on sql server, where [...] is a character class', function (): void {
    $query = queryOnDriver('sqlsrv')->whereLikeEscaped('title', 'a[bc]\\d');

    expect($query->getBindings())->toBe(['%a\\[bc]\\\\d%', '\\']);
});

it('leaves brackets untouched on drivers where they are not a wildcard', function (): void {
    DB::table('articles')->insert(['title' => 'tag [draft]']);

    expect(DB::table('articles')->whereLikeEscaped('title', '[draft]')->pluck('title')->all())->toBe(['tag [draft]'])
        ->and(DB::table('articles')->whereLikeEscaped('title', '[draft]')->getBindings())->toBe(['%[draft]%', '\\']);
});

it('falls back to like on a connection that cannot report its driver', function (): void {
    $connection = Mockery::mock(Illuminate\Database\ConnectionInterface::class);
    $query = new Illuminate\Database\Query\Builder(
        $connection,
        new Illuminate\Database\Query\Grammars\SQLiteGrammar(DB::connection()),
        new Illuminate\Database\Query\Processors\Processor,
    );

    expect($query->from('articles')->whereLikeEscaped('title', 'x')->toSql())
        ->toBe('select * from "articles" where "title" like ? escape ?');
});
