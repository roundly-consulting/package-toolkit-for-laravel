<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Concerns\ResolvesModels;
use RoundlyConsulting\PackageToolkit\Tests\Models\Article;

/**
 * Fixture consumer of the {@see ResolvesModels} convenience trait.
 */
final class ModelRegistry
{
    use ResolvesModels;

    /**
     * @return class-string<Model>
     */
    public function widgetClass(): string
    {
        return $this->modelClass('toolbox.models.widget', Article::class);
    }

    public function widget(): Model
    {
        return $this->newModel('toolbox.models.widget', Article::class);
    }
}
