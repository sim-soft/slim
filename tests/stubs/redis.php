<?php

declare(strict_types=1);

/**
 * Minimal \Redis stub for the test suite.
 *
 * The phpredis extension is optional and is not installed in CI, but
 * RateLimitRedisStorage type-hints \Redis, so the class has to exist before a
 * test double can be built against it. Only the three methods the storage
 * actually calls are declared.
 *
 * Guarded on class_exists so a machine that does have phpredis keeps testing
 * against the real class rather than this stand-in.
 */

if (!class_exists('RedisException', false)) {
    class RedisException extends RuntimeException
    {
    }
}

if (!class_exists('Redis', false)) {
    class Redis
    {
        public function incr(string $key): int|false
        {
            return 1;
        }

        public function expire(string $key, int $timeout): bool
        {
            return true;
        }

        public function ttl(string $key): int|false
        {
            return -1;
        }
    }
}
