<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Redis;
use RedisException;
use RuntimeException;
use Simsoft\Slim\Middlewares\RateLimit;
use Simsoft\Slim\Middlewares\RateLimitRedisStorage;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class RateLimitRedisStorageTest extends TestCase
{
    private function createHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn((new ResponseFactory())->createResponse());
        return $handler;
    }

    #[Test]
    public function incrementReturnsCountAndExpiry(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->method('incr')->willReturn(3);
        $redis->method('ttl')->willReturn(42);

        $result = (new RateLimitRedisStorage($redis))->increment('client-a', 60);

        $this->assertSame(3, $result['count']);
        // The remaining TTL wins over the nominal window, so a client that
        // arrives mid-window is told when this window actually resets.
        $this->assertEqualsWithDelta(time() + 42, $result['expires'], 1);
    }

    #[Test]
    public function setsTtlOnlyOnTheFirstRequestOfAWindow(): void
    {
        // EXPIRE on every hit would slide the window forward indefinitely and
        // a steady stream of traffic would never reset.
        $redis = $this->createMock(Redis::class);
        $redis->method('incr')->willReturn(1);
        $redis->method('ttl')->willReturn(60);
        $redis->expects($this->once())->method('expire')->with($this->anything(), 60);

        (new RateLimitRedisStorage($redis))->increment('client-b', 60);
    }

    #[Test]
    public function doesNotResetTtlOnSubsequentRequests(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->method('incr')->willReturn(2);
        $redis->method('ttl')->willReturn(30);
        $redis->expects($this->never())->method('expire');

        (new RateLimitRedisStorage($redis))->increment('client-c', 60);
    }

    #[Test]
    public function fallsBackToTheWindowWhenTtlIsUnavailable(): void
    {
        // -1 means the key exists with no TTL, -2 that it is already gone.
        $redis = $this->createMock(Redis::class);
        $redis->method('incr')->willReturn(5);
        $redis->method('ttl')->willReturn(-1);

        $result = (new RateLimitRedisStorage($redis))->increment('client-d', 90);

        $this->assertEqualsWithDelta(time() + 90, $result['expires'], 1);
    }

    #[Test]
    public function prefixNamespacesTheKey(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->method('ttl')->willReturn(60);
        $redis->expects($this->once())
            ->method('incr')
            ->with('myapp:rate:' . md5('client-e'))
            ->willReturn(1);

        (new RateLimitRedisStorage($redis, prefix: 'myapp:rate:'))->increment('client-e', 60);
    }

    #[Test]
    public function connectionFailureFailsOpenByDefault(): void
    {
        // phpredis signals a dropped connection by throwing, which previously
        // escaped this class and turned a Redis outage into a 500.
        $redis = $this->createMock(Redis::class);
        $redis->method('incr')->willThrowException(new RedisException('Connection refused'));

        $result = (new RateLimitRedisStorage($redis))->increment('client-f', 60);

        $this->assertSame(1, $result['count']);
    }

    #[Test]
    public function connectionFailureCanFailClosed(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->method('incr')->willThrowException(new RedisException('Connection refused'));

        $result = (new RateLimitRedisStorage($redis, failOpen: false))->increment('client-g', 60);

        $this->assertSame(PHP_INT_MAX, $result['count']);
    }

    #[Test]
    public function falseFromIncrIsTreatedAsAFailure(): void
    {
        // phpredis can be configured not to throw, in which case it reports
        // failure by return value instead. Both routes must end up the same.
        $redis = $this->createMock(Redis::class);
        $redis->method('incr')->willReturn(false);

        $result = (new RateLimitRedisStorage($redis, failOpen: false))->increment('client-h', 60);

        $this->assertSame(PHP_INT_MAX, $result['count']);
    }

    #[Test]
    public function failureDuringExpireIsAlsoCaught(): void
    {
        // The connection can drop between INCR and EXPIRE, not just before it.
        $redis = $this->createMock(Redis::class);
        $redis->method('incr')->willReturn(1);
        $redis->method('expire')->willThrowException(new RedisException('Connection lost'));

        $result = (new RateLimitRedisStorage($redis))->increment('client-i', 60);

        $this->assertSame(1, $result['count']);
    }

    #[Test]
    public function nonRedisExceptionsAreAlsoContained(): void
    {
        // A rate limiter must never be the reason a request 500s, whatever
        // the underlying client decides to throw.
        $redis = $this->createMock(Redis::class);
        $redis->method('incr')->willThrowException(new RuntimeException('something else'));

        $result = (new RateLimitRedisStorage($redis))->increment('client-j', 60);

        $this->assertSame(1, $result['count']);
    }

    #[Test]
    public function connectionFailureIsLogged(): void
    {
        $logger = new CollectingLogger();

        $redis = $this->createMock(Redis::class);
        $redis->method('incr')->willThrowException(new RedisException('Connection refused'));

        (new RateLimitRedisStorage($redis, logger: $logger))->increment('client-k', 60);

        $this->assertCount(1, $logger->messages);
        $this->assertStringContainsString('storage unavailable', $logger->messages[0]);
        $this->assertStringContainsString('failing open', $logger->messages[0]);
        // The underlying reason has to survive into the log, or the operator
        // learns that something broke but not what.
        $this->assertStringContainsString('Connection refused', $logger->messages[0]);
        $this->assertSame('error', $logger->levels[0]);
    }

    #[Test]
    public function failClosedIsLoggedAsSuch(): void
    {
        $logger = new CollectingLogger();

        $redis = $this->createMock(Redis::class);
        $redis->method('incr')->willThrowException(new RedisException('down'));

        (new RateLimitRedisStorage($redis, logger: $logger, failOpen: false))->increment('client-l', 60);

        $this->assertStringContainsString('failing closed', $logger->messages[0]);
        $this->assertFalse($logger->contexts[0]['failOpen']);
    }

    #[Test]
    public function failClosedRedisProduces429(): void
    {
        // End to end: a Redis outage under failOpen:false rejects traffic
        // rather than silently letting it all through.
        $redis = $this->createMock(Redis::class);
        $redis->method('incr')->willThrowException(new RedisException('down'));

        $storage = new RateLimitRedisStorage($redis, failOpen: false);
        $middleware = new RateLimit(maxRequests: 60, windowSeconds: 60, storage: $storage);

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = $middleware($request, $this->createHandler());

        $this->assertSame(429, $response->getStatusCode());
    }

    #[Test]
    public function failOpenRedisStillServesTraffic(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->method('incr')->willThrowException(new RedisException('down'));

        $storage = new RateLimitRedisStorage($redis);
        $middleware = new RateLimit(maxRequests: 60, windowSeconds: 60, storage: $storage);

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = $middleware($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }
}
