<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\Quota;
use Simsoft\Slim\Middlewares\RateLimitFileStorage;
use Slim\Exception\HttpException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class QuotaTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/slim-quota-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->storagePath)) {
            $files = glob($this->storagePath . '/*');
            if ($files) {
                array_map('unlink', $files);
            }
            rmdir($this->storagePath);
        }
    }

    private function createHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn((new ResponseFactory())->createResponse());
        return $handler;
    }

    #[Test]
    public function allowsRequestWithinQuota(): void
    {
        $storage = new RateLimitFileStorage($this->storagePath);
        $middleware = new Quota(
            resolver: fn($request) => 'user:1',
            limit: fn($request, $key) => 5,
            period: 'daily',
            storage: $storage,
        );

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = $middleware($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('5', $response->getHeaderLine('X-Quota-Limit'));
        $this->assertSame('4', $response->getHeaderLine('X-Quota-Remaining'));
    }

    #[Test]
    public function decrementsRemainingOnEachRequest(): void
    {
        $storage = new RateLimitFileStorage($this->storagePath);
        $middleware = new Quota(
            resolver: fn($request) => 'user:1',
            limit: fn($request, $key) => 3,
            period: 'daily',
            storage: $storage,
        );

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $handler = $this->createHandler();

        $r1 = $middleware($request, $handler);
        $r2 = $middleware($request, $handler);
        $r3 = $middleware($request, $handler);

        $this->assertSame('2', $r1->getHeaderLine('X-Quota-Remaining'));
        $this->assertSame('1', $r2->getHeaderLine('X-Quota-Remaining'));
        $this->assertSame('0', $r3->getHeaderLine('X-Quota-Remaining'));
    }

    #[Test]
    public function throwsWhenQuotaExceeded(): void
    {
        $storage = new RateLimitFileStorage($this->storagePath);
        $middleware = new Quota(
            resolver: fn($request) => 'user:1',
            limit: fn($request, $key) => 2,
            period: 'daily',
            storage: $storage,
        );

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $handler = $this->createHandler();

        $middleware($request, $handler);
        $middleware($request, $handler);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(429);

        $middleware($request, $handler);
    }

    #[Test]
    public function differentKeysHaveSeparateCounters(): void
    {
        $storage = new RateLimitFileStorage($this->storagePath);

        $request1 = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request1 = $request1->withAttribute('user', ['id' => '1']);

        $request2 = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request2 = $request2->withAttribute('user', ['id' => '2']);

        $middleware = new Quota(
            resolver: fn($request) => 'user:' . $request->getAttribute('user')['id'],
            limit: fn($request, $key) => 3,
            period: 'daily',
            storage: $storage,
        );

        $handler = $this->createHandler();

        $r1 = $middleware($request1, $handler);
        $r2 = $middleware($request1, $handler);
        $r3 = $middleware($request2, $handler);

        $this->assertSame('2', $r1->getHeaderLine('X-Quota-Remaining'));
        $this->assertSame('1', $r2->getHeaderLine('X-Quota-Remaining'));
        $this->assertSame('2', $r3->getHeaderLine('X-Quota-Remaining'));
    }

    #[Test]
    public function dynamicLimitPerKey(): void
    {
        $storage = new RateLimitFileStorage($this->storagePath);

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withAttribute('user', ['id' => '1', 'plan' => 'pro']);

        $middleware = new Quota(
            resolver: fn($request) => 'user:' . $request->getAttribute('user')['id'],
            limit: function ($request, $key) {
                $user = $request->getAttribute('user');
                return match ($user['plan']) {
                    'free' => 100,
                    'pro' => 5000,
                    default => 100,
                };
            },
            period: 'monthly',
            storage: $storage,
        );

        $response = $middleware($request, $this->createHandler());

        $this->assertSame('5000', $response->getHeaderLine('X-Quota-Limit'));
        $this->assertSame('4999', $response->getHeaderLine('X-Quota-Remaining'));
    }

    #[Test]
    public function setsResetHeader(): void
    {
        $storage = new RateLimitFileStorage($this->storagePath);
        $middleware = new Quota(
            resolver: fn($request) => 'user:1',
            limit: fn($request, $key) => 1000,
            period: 'daily',
            storage: $storage,
        );

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = $middleware($request, $this->createHandler());

        $reset = (int)$response->getHeaderLine('X-Quota-Reset');
        $this->assertGreaterThan(time(), $reset);
        $this->assertLessThanOrEqual(time() + 86400, $reset);
    }

    #[Test]
    public function customHeaderPrefix(): void
    {
        $storage = new RateLimitFileStorage($this->storagePath);
        $middleware = new Quota(
            resolver: fn($request) => 'user:1',
            limit: fn($request, $key) => 100,
            period: 'daily',
            storage: $storage,
            headerPrefix: 'X-API-Quota',
        );

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = $middleware($request, $this->createHandler());

        $this->assertTrue($response->hasHeader('X-API-Quota-Limit'));
        $this->assertTrue($response->hasHeader('X-API-Quota-Remaining'));
        $this->assertTrue($response->hasHeader('X-API-Quota-Reset'));
    }

    #[Test]
    public function periodParsing(): void
    {
        $storage = new RateLimitFileStorage($this->storagePath);

        // Monthly period — reset should be ~30 days from now
        $middleware = new Quota(
            resolver: fn($request) => 'user:monthly',
            limit: fn($request, $key) => 1000,
            period: 'monthly',
            storage: $storage,
        );

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = $middleware($request, $this->createHandler());

        $reset = (int)$response->getHeaderLine('X-Quota-Reset');
        $this->assertGreaterThan(time() + 2500000, $reset); // > ~29 days
    }
}
