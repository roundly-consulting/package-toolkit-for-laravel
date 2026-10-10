<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Tests\Support;

use Closure;
use PHPUnit\Framework\Assert;
use Throwable;

/**
 * What a call threw, recorded with `zend.exception_ignore_args` **off**, plus a way to ask
 * where a secret shows up in it.
 *
 * Production ini turns `zend.exception_ignore_args` on, which strips every frame's arguments:
 * a leak test run that way passes over nothing. {@see self::by()} switches it off for the call
 * only, and {@see self::frame()} proves the frames it inspects really carry arguments.
 *
 * Only the frames that ran **inside** the call are inspected — the code under test is
 * answerable for those. The frames above belong to the test harness, and they hold the test's
 * own closures, which capture the very secret under test.
 */
final readonly class Thrown
{
    /**
     * @param  int  $outside  how many frames of every trace sit outside the call
     */
    private function __construct(
        public Throwable $exception,
        private int $outside,
    ) {}

    public static function by(Closure $call): self
    {
        // The frames from here up, plus the one that invokes $call, are the harness's.
        $outside = count(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)) + 1;
        $previous = ini_set('zend.exception_ignore_args', '0');

        try {
            $call();
        } catch (Throwable $e) {
            return new self($e, $outside);
        } finally {
            ini_set('zend.exception_ignore_args', (string) $previous);
        }

        Assert::fail('Expected the call to throw, but it returned.');
    }

    /**
     * Where `$needle` shows in a message, a trace string or a frame's arguments — of the
     * exception or of any exception chained behind it.
     *
     * @return list<string>
     */
    public function anywhere(string $needle): array
    {
        $found = [];

        foreach ($this->chain() as $label => $exception) {
            if (str_contains($exception->getMessage(), $needle)) {
                $found[] = "{$label}: message";
            }
        }

        return [...$found, ...$this->inTrace($needle)];
    }

    /**
     * Where `$needle` shows in a trace string or a frame's arguments — of the exception or of
     * any exception chained behind it. Arguments are rendered the way a careless error tracker
     * would (`print_r`), so a value behind `SensitiveParameterValue` stays hidden and anything
     * else is found, however deeply nested.
     *
     * @return list<string>
     */
    public function inTrace(string $needle): array
    {
        $found = [];

        foreach ($this->chain() as $label => $exception) {
            $frames = $this->inside($exception);

            // One line per frame, innermost first; strings in it are escaped onto that line.
            $lines = array_slice(explode("\n", $exception->getTraceAsString()), 0, count($frames));

            if (str_contains(implode("\n", $lines), $needle)) {
                $found[] = "{$label}: getTraceAsString()";
            }

            foreach ($frames as $index => $frame) {
                if (str_contains(print_r($frame['args'] ?? [], true), $needle)) {
                    $found[] = "{$label}: frame #{$index} ".self::name($frame).'() arguments';
                }
            }
        }

        return $found;
    }

    /**
     * The arguments of the first frame inside the call that invoked `$function`. Fails when
     * there is none, or when it carries no arguments — which would mean the trace was recorded
     * with arguments stripped, and every "absent" above proved nothing.
     *
     * @return array<array-key, mixed>
     */
    public function frame(string $function): array
    {
        foreach ($this->inside($this->exception) as $frame) {
            if ($frame['function'] === $function) {
                Assert::assertArrayHasKey('args', $frame, "The {$function}() frame was recorded without arguments.");
                Assert::assertNotSame([], $frame['args'], "The {$function}() frame was recorded without arguments.");

                return $frame['args'];
            }
        }

        Assert::fail("No {$function}() frame inside the call.");
    }

    /**
     * @return array<string, Throwable>
     */
    private function chain(): array
    {
        $chain = [];

        for ($e = $this->exception, $depth = 0; $e !== null; $e = $e->getPrevious(), $depth++) {
            $chain[$depth === 0 ? $e::class : "previous #{$depth} ".$e::class] = $e;
        }

        return $chain;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inside(Throwable $exception): array
    {
        $trace = $exception->getTrace();

        return array_slice($trace, 0, max(0, count($trace) - $this->outside));
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private static function name(array $frame): string
    {
        return ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'];
    }
}
