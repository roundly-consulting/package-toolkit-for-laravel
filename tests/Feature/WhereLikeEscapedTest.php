<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Tests\Models\Article;

beforeEach(function (): void {
    DB::table('articles')->insert([
        ['title' => 'hello world'],
        ['title' => 'he%lo there'],
        ['title' => 'completely different'],
    ]);
});

afterEach(function (): void {
    Schema::dropIfExists('lookups');
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

it('casts the column to text on postgres, as Laravel\'s own like does', function (): void {
    $connection = new Illuminate\Database\PostgresConnection(new PDO('sqlite::memory:'), '', '', ['driver' => 'pgsql', 'name' => 'pgsql']);

    $query = $connection->query()->from('articles')->whereLikeEscaped('title', '50%_off');

    // Postgres has no `uuid ~~* text` or `bigint ~~* text` operator: without the cast a
    // search on a uuid or integer column throws SQLSTATE 42883 on a request path.
    expect($query->toSql())->toBe('select * from "articles" where "title"::text ilike ? escape ?')
        ->and($query->getBindings())->toBe(['%50\\%\\_off%', '\\']);
});

it('searches a uuid and an integer column on the active driver', function (): void {
    Schema::create('lookups', function (Blueprint $table): void {
        $table->id();
        $table->uuid('reference');
    });

    DB::table('lookups')->insert([
        ['id' => 1234, 'reference' => '9b2c7f4e-0d1a-4c3b-8e5f-6a7b8c9d0e1f'],
        ['id' => 5678, 'reference' => '1f0e9d8c-7b6a-4f5e-8d3c-2b1a0f9e8d7c'],
    ]);

    expect(DB::table('lookups')->whereLikeEscaped('reference', '4C3B')->pluck('id')->all())->toEqual([1234])
        ->and(DB::table('lookups')->whereLikeEscaped('id', '67')->pluck('id')->all())->toEqual([5678]);
});
