<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Log\LoggerInterface;
use Redis;
use Throwable;

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
    use ReportsStorageFailure;

    /** @var string Key prefix for rate limit entries. */
    protected string $prefix;

    /**
     * Constructor.
     *
     * @param Redis $redis Redis connection instance.
     * @param string $prefix Key prefix for namespacing.
     * @param LoggerInterface|null $logger Logger notified when Redis is unreachable.
     * @param bool $failOpen Whether to allow requests through when Redis is unreachable.
     *                       True (default) favours availability: a Redis outage will not
     *                       take the site down, but the limiter stops limiting. False
     *                       favours protection: requests are rejected while Redis is
     *                       down. Prefer false for endpoints where abuse is costlier
     *                       than downtime.
     */
    public function __construct(
        protected Redis $redis,
        string $prefix = 'rate_limit:',
        ?LoggerInterface $logger = null,
        bool $failOpen = true,
    ) {
        $this->prefix = $prefix;
        $this->logger = $logger;
        $this->failOpen = $failOpen;
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

        try {
            /** @var int|false $count */
            $count = $this->redis->incr($key);

            // phpredis normally throws on a dead connection, but it can also
            // return false (notably when the client is configured not to throw),
            // so both routes have to end at the same policy.
            if ($count === false) {
                return $this->storageFailure('INCR returned false', $windowSeconds, ['key' => $key]);
            }

            if ($count === 1) {
                $this->redis->expire($key, $windowSeconds);
            }

            /** @var int|false $ttl */
            $ttl = $this->redis->ttl($key);
        } catch (Throwable $e) {
            // RedisException is the expected case (a dropped connection is the
            // whole reason this policy exists), but a rate limiter must never be
            // the thing that takes a request down, so nothing escapes from here.
            return $this->storageFailure($e->getMessage(), $windowSeconds, ['key' => $key]);
        }

        return [
            'count' => $count,
            'expires' => time() + (is_int($ttl) && $ttl > 0 ? $ttl : $windowSeconds),
        ];
    }
}
