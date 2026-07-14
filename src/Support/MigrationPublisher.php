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
     * The host path the named migration publishes to: the file it was already
     * published to when one exists (so republishing overwrites in place), and a
     * freshly timestamped `<Y_m_d_His>_<name>.php` otherwise.
     */
    public static function destination(string $name, string $directory, CarbonInterface $timestamp): string
    {
        $directory = rtrim($directory, '/');

        return self::publishedFile($name, $directory)
            ?? $directory.'/'.$timestamp->format('Y_m_d_His').'_'.$name.'.php';
    }

    /**
     * An already-published copy of the named migration in the host's migrations
     * directory, whatever timestamp it carries.
     */
    private static function publishedFile(string $name, string $directory): ?string
    {
        $files = glob($directory.'/*.php') ?: [];

        sort($files);

        foreach ($files as $file) {
            if (self::nameFor($file) === $name) {
                return $file;
            }
        }

        return null;
    }
}
