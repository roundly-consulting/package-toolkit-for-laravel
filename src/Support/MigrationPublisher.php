<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Support;

use Carbon\CarbonInterface;

/**
 * Resolves where a package migration publishes to in the host application.
 *
 * Packages never auto-load their migrations: they publish them and the host runs
 * `php artisan migrate`. A published file therefore has to carry a timestamp so
 * it orders against the host's own migrations, and republishing has to land on
 * the *same* file it landed on last time — otherwise a second
 * `vendor:publish --tag=<pkg>-migrations` would drop a second, differently
 * timestamped copy of the same `Schema::create()` and the migrator (which keys
 * by filename) would run both.
 *
 * A name alone does not prove a host file is that earlier copy: the host (or
 * another package) may own an unrelated `create_comments_table` migration. Only
 * a same-named file whose contents are the package source's is reused, so
 * `vendor:publish --force` can never overwrite a migration the package did not
 * put there.
 */
final class MigrationPublisher
{
    private const TIMESTAMP_PREFIX = '/^\d{4}_\d{2}_\d{2}_\d{6}_/';

    /**
     * The migration name a package source file publishes under: its base name
     * without the `.php` / `.php.stub` extension and without any timestamp the
     * package itself prefixed (the publish timestamp replaces it).
     */
    public static function nameFor(string $path): string
    {
        $name = basename($path);

        if (str_ends_with($name, '.stub')) {
            $name = substr($name, 0, -5);
        }

        if (str_ends_with($name, '.php')) {
            $name = substr($name, 0, -4);
        }

        return preg_replace(self::TIMESTAMP_PREFIX, '', $name) ?? $name;
    }

    /**
     * The host path the package migration at `$source` publishes to: the file
     * it was already published to when one exists (so republishing overwrites
     * in place), and a freshly timestamped `<Y_m_d_His>_<name>.php` otherwise.
     */
    public static function destination(string $source, string $directory, CarbonInterface $timestamp): string
    {
        $directory = rtrim($directory, '/');
        $name = self::nameFor($source);

        return self::publishedFile($source, $name, $directory)
            ?? $directory.'/'.$timestamp->format('Y_m_d_His').'_'.$name.'.php';
    }

    /**
     * An already-published copy of the migration in the host's migrations
     * directory, whatever timestamp it carries: a file of the same name whose
     * contents are the package source's.
     */
    private static function publishedFile(string $source, string $name, string $directory): ?string
    {
        $files = array_filter(
            glob($directory.'/*.php') ?: [],
            static fn (string $file): bool => self::nameFor($file) === $name,
        );

        if ($files === []) {
            return null;
        }

        $signature = self::signature($source);

        if ($signature === null) {
            return null;
        }

        sort($files);

        foreach ($files as $file) {
            if (self::signature($file) === $signature) {
                return $file;
            }
        }

        return null;
    }

    /**
     * A file's contents with all whitespace removed, so a copy that only picked
     * up different line endings (git `autocrlf`) or re-indentation still counts
     * as the same migration. Null when the file cannot be read.
     */
    private static function signature(string $file): ?string
    {
        $contents = is_file($file) ? file_get_contents($file) : false;

        return $contents === false ? null : preg_replace('/\s+/', '', $contents);
    }
}
