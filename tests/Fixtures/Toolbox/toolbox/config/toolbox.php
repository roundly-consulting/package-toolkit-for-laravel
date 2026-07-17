<?php

declare(strict_types=1);

return [
    'key_type' => 'uuid',

    /*
     * A credential-shaped value, so the `about` contribution the toolkit registers has
     * something it is actually required not to leak. Purchases #13 shipped the fleet's
     * most credential-heavy about section behind a leak check that read `''`; the toolkit
     * is the machinery every one of those sections is registered through, so its own
     * fixture carries a secret rather than pretending none exists.
     */
    'credential' => 'tk_live_ea9b8d66_never_render_me',

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
