<?php

declare(strict_types=1);

namespace RoundlyConsulting\PackageToolkit\Contracts;

/**
 * Implemented by exceptions (or other signals) that carry a retry-after hint,
 * so a host can translate a rate-limit failure into a `Retry-After` header
 * without coupling to any particular base exception.
 */
interface HasRetryAfter
{
    /**
     * The number of seconds the caller should wait before retrying.
     */
    public function retryAfterSeconds(): int;
}
