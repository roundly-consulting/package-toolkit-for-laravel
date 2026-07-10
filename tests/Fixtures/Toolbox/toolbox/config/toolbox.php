<?php

declare(strict_types=1);

return [
    'key_type' => 'uuid',

    'alias' => true,

    'disabled_alias' => false,

    'routes' => [
        'enabled' => true,
        'disabled' => false,
    ],

    'models' => [
        'widget' => null,
    ],

    'greeter' => RoundlyConsulting\PackageToolkit\Tests\Fixtures\Toolbox\EnglishGreeter::class,

    'observed_model' => RoundlyConsulting\PackageToolkit\Tests\Models\Article::class,
];
