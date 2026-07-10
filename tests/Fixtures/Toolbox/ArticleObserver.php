<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox;

use RoundlyConsulting\PackageToolkit\Tests\Models\Article;

final class ArticleObserver
{
    public static int $created = 0;

    public function created(Article $article): void
    {
        self::$created++;
    }
}
