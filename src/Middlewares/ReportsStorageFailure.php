<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Log\LoggerInterface;

/**
 * ReportsStorageFailure Trait
 *
 * Shared failure policy for rate limit storage backends.
 *
 * When a backend cannot reach its store it has to choose between serving an
 * uncounted request and rejecting it. Both answers are defensible, but they
 * must be the same answer everywhere: a limiter that fails open on Redis and
 * closed on disk is a limiter nobody can reason about. Backends implementing
 * RateLimitStorageInterface can use this trait to inherit that decision.
 */
trait ReportsStorageFailure
{
    /** @var LoggerInterface|null Notified whenever storage is unavailable. */
    protected ?LoggerInterface $logger = null;

    /** @var bool Whether unreachable storage lets requests through. */
    protected bool $failOpen = true;

    /**
     * Log an unreachable store and apply the configured failure policy.
     *
     * Failing open is silent by nature: the limiter stops limiting and every
     * request still succeeds, so nothing surfaces unless it is logged here.
     *
     * @param string $reason What went wrong, for the log.
     * @param int $windowSeconds Time window duration in seconds.
     * @param array<string, scalar|null> $context Extra log context.
     * @return array{count: int, expires: int} A count that either passes any
     *                                         limit (open) or exceeds every
     *                                         limit (closed).
     */
    protected function storageFailure(string $reason, int $windowSeconds, array $context = []): array
    {
        $this->logger?->error(
            'Rate limit storage unavailable; ' . ($this->failOpen ? 'failing open' : 'failing closed') . ': ' . $reason,
            $context + ['failOpen' => $this->failOpen],
        );

        return [
            'count' => $this->failOpen ? 1 : PHP_INT_MAX,
            'expires' => time() + $windowSeconds,
        ];
    }
}
