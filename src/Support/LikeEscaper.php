<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Support;

/**
 * Escapes user input for a portable, injection-safe `LIKE`/`ILIKE` search.
 */
final class LikeEscaper
{
    /**
     * Escape the `LIKE` wildcards `%` and `_` (and the escape char `\`) in user
     * input so a search term can never widen the match beyond a literal
     * substring. The escaped string is meant to be wrapped in `%...%` by the
     * caller and matched with an explicit `LIKE ? ESCAPE '\'` clause: SQLite has
     * no default escape character, so without the explicit clause the escaping
     * backslashes stay literal and the wildcards remain live.
     */
    public static function escape(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value,
        );
    }
}
