<?php

// Deliberately no strict_types (exempt in tests/ArchTest.php and pint.json): Laravel's own
// Facade::__callStatic forwards from a file without it, so `Facade::method(123)` coerces for
// every caller. Forwarding from a strict file would turn those calls into TypeErrors.

namespace RoundlyConsulting\PackageToolkit\Concerns;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\PackageToolkit\Support\SensitiveArguments;
use RuntimeException;
use SensitiveParameterValue;

/**
 * For a facade whose root takes secrets: hides, in the facade's own stack frame, exactly the
 * arguments the root method marks `#[SensitiveParameter]`.
 *
 * PHP redacts such an argument only in the frame of the function that declares it. A call
 * through a stock facade passes `Facade::__callStatic($method, $args)` first, and that frame
 * carries every argument raw, so `Crypto::constantTimeEquals($known, $input)` put `$known`
 * where an error tracker that collects frame arguments, `print_r($e)` or a trace logger finds
 * it. This trait replaces `__callStatic`: it wraps each sensitive entry of its own `$args` in
 * a `SensitiveParameterValue` (a backtrace shows a parameter's current value) and forwards an
 * untouched copy. Harmless arguments stay visible. Named arguments, a sensitive variadic and an
 * exception thrown while the root resolves are covered.
 *
 * The positions come from the **accessor type**, the class or interface `getFacadeAccessor()`
 * names. A swapped fake or a Mockery mock whose override drops the attribute is therefore
 * still redacted in the facade frame, and still receives the real values. An accessor that is
 * not a class-string, or a method the type does not declare (a macro), forwards unredacted, as
 * Laravel's own facade does.
 *
 * ```php
 * final class Crypto extends Facade
 * {
 *     use RedactsSensitiveArguments;
 *
 *     protected static function getFacadeAccessor(): string
 *     {
 *         return CryptoManager::class;
 *     }
 * }
 * ```
 *
 * @phpstan-require-extends Facade
 */
trait RedactsSensitiveArguments
{
    /**
     * Per facade class and method: where the sensitive arguments sit, or false for none (a
     * cached null would look like a miss, and reflect again on every call).
     *
     * @var array<string, array<string, SensitiveArguments|false>>
     */
    private static array $sensitiveArguments = [];

    /**
     * Laravel's `Facade::__callStatic()`, with the sensitive arguments hidden in this frame.
     * Never call `parent::__callStatic()` from here: its frame would carry them raw again.
     *
     * @param  string  $method
     * @param  array<array-key, mixed>  $args
     * @return mixed
     *
     * @throws RuntimeException
     */
    public static function __callStatic($method, $args)
    {
        $forward = $args;
        $sensitive = self::$sensitiveArguments[static::class][$method]
            ??= SensitiveArguments::of(static::getFacadeAccessor(), $method) ?? false;

        // Before the root resolves, so a root that fails to resolve cannot leak them either.
        if ($sensitive !== false) {
            foreach ($sensitive->positions as $position) {
                if (array_key_exists($position, $args)) {
                    $args[$position] = new SensitiveParameterValue($args[$position]);
                }
            }

            if ($sensitive->variadic !== null || ! array_is_list($args)) {
                $args = $sensitive->redactNamedAndVariadic($args);
            }
        }

        $instance = static::getFacadeRoot();

        if (! $instance) {
            throw new RuntimeException('A facade root has not been set.');
        }

        return $instance->$method(...$forward);
    }
}
