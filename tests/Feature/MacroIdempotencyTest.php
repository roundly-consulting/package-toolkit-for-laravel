<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\PackageToolkit\Concerns\InteractsWithGates;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBladeDirectives;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;

/**
 * Exposes the protected trait methods so a test can drive them directly.
 */
function macroHarness(): object
{
    return new class
    {
        use InteractsWithGates;
        use RegistersBladeDirectives;
        use RegistersBlueprintMacros;

        public function macros(): void
        {
            $this->registerBlueprintMacros();
        }

        public function directive(string $name, callable $handler): void
        {
            $this->registerBladeDirective($name, $handler);
        }

        public function gate(string $ability, callable $callback): void
        {
            $this->defineGate($ability, $callback);
        }

        public function bladeIf(string $name, callable $condition): void
        {
            $this->registerBladeIf($name, $condition);
        }
    };
}

it('is safe to register the blueprint macros twice', function (): void {
    $harness = macroHarness();

    $harness->macros();
    $harness->macros();

    expect(Blueprint::hasMacro('ownerKey'))->toBeTrue();
});

it('does not clobber an already-registered blade directive', function (): void {
    $harness = macroHarness();

    $harness->directive('toolboxTag', static fn (): string => "<?php echo 'first'; ?>");
    $harness->directive('toolboxTag', static fn (): string => "<?php echo 'second'; ?>");

    $directive = Blade::getCustomDirectives()['toolboxTag'];

    expect($directive(''))->toBe("<?php echo 'first'; ?>");
});

it('does not clobber an already-registered blade conditional on a double boot', function (): void {
    $harness = macroHarness();

    $harness->bladeIf('toolboxFlag', static fn (): bool => true);
    $harness->bladeIf('toolboxFlag', static fn (): bool => false);

    expect(trim(Blade::render('@toolboxFlag yes @else no @endtoolboxFlag')))->toBe('yes');
});

it('leaves a plain directive alone when a conditional claims the same name', function (): void {
    $harness = macroHarness();

    $harness->directive('toolboxClaimed', static fn (): string => "<?php echo 'directive'; ?>");
    $harness->bladeIf('toolboxClaimed', static fn (): bool => true);

    expect(trim(Blade::render('@toolboxClaimed')))->toBe('directive');
});

it('does not let a plain directive clobber a registered conditional', function (): void {
    $harness = macroHarness();

    $harness->bladeIf('toolboxGuarded', static fn (): bool => true);
    $harness->directive('toolboxGuarded', static fn (): string => "<?php echo 'directive'; ?>");

    expect(trim(Blade::render('@toolboxGuarded yes @else no @endtoolboxGuarded')))->toBe('yes');
});

it('does not clobber an already-defined gate', function (): void {
    $harness = macroHarness();

    $harness->gate('toolbox-thing', static fn (mixed $user): bool => true);
    $harness->gate('toolbox-thing', static fn (mixed $user): bool => false);

    expect(Gate::forUser(new stdClass)->allows('toolbox-thing'))->toBeTrue();
});
