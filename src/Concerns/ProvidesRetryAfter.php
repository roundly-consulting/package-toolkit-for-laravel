<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Concerns;

/**
 * Default implementation of {@see \RoundlyConsulting\PackageToolkit\Contracts\HasRetryAfter}.
 * Stores the retry delay and exposes it, without coupling the using class to
 * any base exception.
 */
trait ProvidesRetryAfter
{
    protected int $retryAfter = 0;

    /**
     * Set the retry delay in seconds (clamped to a non-negative value).
     */
    public function withRetryAfter(int $seconds): static
    {
        $this->retryAfter = max(0, $seconds);

        return $this;
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfter;
    }
}
