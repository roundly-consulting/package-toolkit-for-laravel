<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Support;

use ReflectionMethod;
use RoundlyConsulting\PackageToolkit\Concerns\RedactsSensitiveArguments;
use SensitiveParameter;
use SensitiveParameterValue;

/**
 * Where a method's `#[SensitiveParameter]` arguments sit, and how to hide them in an argument
 * array that is forwarded to it.
 *
 * @internal the lookup behind {@see RedactsSensitiveArguments}
 */
final readonly class SensitiveArguments
{
    /**
     * @param  list<int>  $positions  where the marked parameters sit
     * @param  array<string, true>  $names  the marked parameters' names
     * @param  array<string, true>  $declared  every parameter's name
     * @param  int|null  $variadic  where a marked variadic sits; every later positional argument is its
     * @param  bool  $unknownNamesAreSensitive  false only when a harmless variadic collects unknown names
     */
    private function __construct(
        public array $positions,
        public array $names,
        public array $declared,
        public ?int $variadic,
        public bool $unknownNamesAreSensitive,
    ) {}

    /**
     * The sensitive arguments of `$class::$method()`, or null when it marks none — or does not
     * declare the method at all: a macro the root forwards through `__call`, or an accessor
     * that is not a class.
     */
    public static function of(string $class, string $method): ?self
    {
        if (! method_exists($class, $method)) {
            return null;
        }

        $positions = [];
        $names = [];
        $declared = [];
        $variadic = null;
        $unknownNamesAreSensitive = true;

        foreach ((new ReflectionMethod($class, $method))->getParameters() as $parameter) {
            $declared[$parameter->getName()] = true;
            $sensitive = $parameter->getAttributes(SensitiveParameter::class) !== [];

            if ($sensitive) {
                $positions[] = $parameter->getPosition();
                $names[$parameter->getName()] = true;
            }

            if ($parameter->isVariadic()) {
                $variadic = $sensitive ? $parameter->getPosition() : null;
                $unknownNamesAreSensitive = $sensitive;
            }
        }

        return $positions === [] ? null : new self($positions, $names, $declared, $variadic, $unknownNamesAreSensitive);
    }

    /**
     * `$args` with every sensitive named argument (string key) and the tail a sensitive
     * variadic collects wrapped in `SensitiveParameterValue`; entries already wrapped are left
     * alone. A name the method does not declare counts as sensitive unless a harmless variadic
     * collects it: without a variadic the call refuses it anyway, and a misspelt secret is the
     * likeliest one.
     *
     * @param  array<array-key, mixed>  $args
     * @return array<array-key, mixed>
     */
    public function redactNamedAndVariadic(#[SensitiveParameter] array $args): array
    {
        foreach ($args as $key => $value) {
            if ($value instanceof SensitiveParameterValue) {
                continue;
            }

            $sensitive = is_int($key)
                ? $this->variadic !== null && $key >= $this->variadic
                : isset($this->names[$key]) || (! isset($this->declared[$key]) && $this->unknownNamesAreSensitive);

            if ($sensitive) {
                $args[$key] = new SensitiveParameterValue($value);
            }
        }

        return $args;
    }
}
