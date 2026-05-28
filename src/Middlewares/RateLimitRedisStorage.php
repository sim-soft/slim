<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Redis;

/**
 * RateLimitRedisStorage Class
 *
 * Redis-based rate limit storage using atomic INCR + EXPIRE.
 * Suitable for distributed/multi-server deployments.
 *
 * Requires the phpredis extension.
 */
class RateLimitRedisStorage implements RateLimitStorageInterface
{
    /** @var string Key prefix for rate limit entries. */
    protected string $prefix;

    /**
     * Constructor.
     *
     * @param Redis $redis Redis connection instance.
     * @param string $prefix Key prefix for namespacing.
     */
    public function __construct(
        protected Redis $redis,
        string          $prefix = 'rate_limit:',
    )
    {
        $this->prefix = $prefix;
    }

    /**
     * Atomically increment using Redis INCR with TTL.
     *
     * @param string $clientId
     * @param int $windowSeconds
     * @return array{count: int, expires: int}
     */
    public function increment(string $clientId, int $windowSeconds): array
    {
        $key = $this->prefix . md5($clientId);
        $now = time();

        /** @var int|false $count */
        $count = $this->redis->incr($key);

        if ($count === false) {
            return ['count' => 1, 'expires' => $now + $windowSeconds];
        }

        if ($count === 1) {
            $this->redis->expire($key, $windowSeconds);
        }

        /** @var int|false $ttl */
        $ttl = $this->redis->ttl($key);
        $expires = $now + (is_int($ttl) && $ttl > 0 ? $ttl : $windowSeconds);

        return ['count' => $count, 'expires' => $expires];
    }
}
