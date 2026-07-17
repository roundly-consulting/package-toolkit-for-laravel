<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\PackageToolkitException;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * The toolkit shipped with no architecture test at all, so every preset here is a new
 * guard rather than a replacement — on the package that sits in the `require` of all 45
 * others. Anything that rots here rots everywhere at once.
 *
 * Two of the seven presets do not apply and are deliberately **not** registered rather
 * than added for symmetry:
 *
 *   - `swappableModelsAreNotFinal` — the toolkit ships no model. It ships `ModelResolver`,
 *     the seam a *consumer's* model is swapped through, so there is nothing to map.
 *   - `modelsResolveThroughSeam` — same root cause: no Eloquent model, no `*_model` config
 *     key of its own, so the late-static-binding ban and the stray-literal scan both have
 *     nothing to read. It would be green on the first run and green forever. jwt and enums
 *     rejected it for the same reason; shops and credits adopted it because they have the
 *     shape it targets — a real model behind a `*_model` key.
 */
ArchPresets::strictTypes('RoundlyConsulting\PackageToolkit');

/**
 * Two deliberate extension points are exempt, and they are the toolkit's entire purpose:
 * PackageServiceProvider, the abstract base every roundly provider extends, and
 * PackageToolkitException, the abstract base every toolkit error extends so a host can
 * catch them uniformly. Everything else stays closed.
 */
ArchPresets::finalByDefault('RoundlyConsulting\PackageToolkit', [
    PackageServiceProvider::class,
    PackageToolkitException::class,
]);

/**
 * The toolkit does no cryptography. The ban matters more here than anywhere: `KeyType`
 * hands out uuid/ulid key columns, so a hand-rolled id scheme would be a plausible
 * addition — and it belongs in crypto-for-laravel, not in the base 45 packages inherit.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\PackageToolkit');

/**
 * The Dependency Policy as a test, and the single highest-leverage assertion in the fleet:
 * every package `require`s the toolkit, so a third-party vendor entering *this* `require`
 * is installed transitively into every host app of every roundly package. No `alsoAllow` —
 * the toolkit's `require` is php + illuminate/* and must stay that way. The dev-only
 * carve-out covers testing-for-laravel alone, and that sits in `require-dev`, which this
 * preset does not read. If this goes red, the graph is wrong; never widen the allow-list.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();
