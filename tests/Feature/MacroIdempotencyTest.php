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

it('does not clobber an already-defined gate', function (): void {
    $harness = macroHarness();

    $harness->gate('toolbox-thing', static fn (mixed $user): bool => true);
    $harness->gate('toolbox-thing', static fn (mixed $user): bool => false);

    expect(Gate::forUser(new stdClass)->allows('toolbox-thing'))->toBeTrue();
});
