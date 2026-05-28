<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

/**
 * RateLimitStorageInterface
 *
 * Contract for rate limit storage backends.
 */
interface RateLimitStorageInterface
{
    /**
     * Atomically increment the request count for a client within a time window.
     *
     * @param string $clientId Unique client identifier.
     * @param int $windowSeconds Time window duration in seconds.
     * @return array{count: int, expires: int} Current count and window expiry timestamp.
     */
    public function increment(string $clientId, int $windowSeconds): array;
}
