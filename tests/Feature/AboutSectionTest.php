<?php

declare(strict_types=1);

/**
 * The secret-safe `about` capture (A).
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`, which
 * returns `''`. Every "does not leak" check was vacuous, passing against empty output.
 *
 * The toolkit is a special case worth stating. It ships no `about` section of its own — it
 * ships `contributesToAbout()`, the machinery **every** package's section is registered
 * through. So the thing under test is the registration itself: that a host-supplied
 * closure is evaluated at render time and renders exactly what it returned, and that the
 * raw config it read never rides along. The Toolbox fixture stands in for the 45 real
 * providers, and carries a credential precisely so the negative half has something to
 * prove.
 */
it('renders the toolbox section without leaking the credential behind it', function (): void {
    expect('Toolbox')->toLeakNoSecrets(
        secrets: [
            // The raw credential the about closure redacts. If this ever renders, every
            // package that reports a key through contributesToAbout() leaks it too.
            'tk_live_ea9b8d66_never_render_me',
        ],
        mustRender: [
            // Positive proof the section really rendered before any secret check runs.
            'Package',
            'toolbox',
            'Key type',
            'uuid',
            'Greeter',
            'EnglishGreeter',
            // The redacted form must be what renders — the label alone would still be
            // present if the value had been dropped entirely.
            'Credential',
            'tk_live',
        ],
    );
});
