<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\RateLimit;
use Simsoft\Slim\Middlewares\RateLimitFileStorage;
use Simsoft\Slim\Middlewares\RateLimitStorageInterface;
use Slim\Exception\HttpException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class RateLimitTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/slim-rate-limit-test-' . uniqid();
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
    public function allowsRequestsWithinLimit(): void
    {
        $storage = new RateLimitFileStorage($this->storagePath);
        $middleware = new RateLimit(maxRequests: 5, windowSeconds: 60, storage: $storage);

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withAttribute('REMOTE_ADDR', '127.0.0.1');

        $response = $middleware($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('5', $response->getHeaderLine('X-RateLimit-Limit'));
        $this->assertSame('4', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    #[Test]
    public function decrementsRemainingOnEachRequest(): void
    {
        $storage = new RateLimitFileStorage($this->storagePath);
        $middleware = new RateLimit(maxRequests: 3, windowSeconds: 60, storage: $storage);

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $handler = $this->createHandler();

        $response1 = $middleware($request, $handler);
        $response2 = $middleware($request, $handler);
        $response3 = $middleware($request, $handler);

        $this->assertSame('2', $response1->getHeaderLine('X-RateLimit-Remaining'));
        $this->assertSame('1', $response2->getHeaderLine('X-RateLimit-Remaining'));
        $this->assertSame('0', $response3->getHeaderLine('X-RateLimit-Remaining'));
    }

    #[Test]
    public function throwsHttpExceptionWhenLimitExceeded(): void
    {
        $storage = new RateLimitFileStorage($this->storagePath);
        $middleware = new RateLimit(maxRequests: 2, windowSeconds: 60, storage: $storage);

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $handler = $this->createHandler();

        $middleware($request, $handler);
        $middleware($request, $handler);

        $this->expectException(HttpException::class);
        $middleware($request, $handler);
    }

    #[Test]
    public function setsResetHeader(): void
    {
        $storage = new RateLimitFileStorage($this->storagePath);
        $middleware = new RateLimit(maxRequests: 10, windowSeconds: 120, storage: $storage);

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = $middleware($request, $this->createHandler());

        $reset = (int)$response->getHeaderLine('X-RateLimit-Reset');
        $this->assertGreaterThan(time(), $reset);
        $this->assertLessThanOrEqual(time() + 120, $reset);
    }

    #[Test]
    public function respectsTrustedProxyForwardedFor(): void
    {
        $storage = $this->createMock(RateLimitStorageInterface::class);
        $storage->expects($this->once())
            ->method('increment')
            ->with('10.0.0.1', 60)
            ->willReturn(['count' => 1, 'expires' => time() + 60]);

        $middleware = new RateLimit(
            maxRequests: 60,
            windowSeconds: 60,
            storage: $storage,
            trustedProxies: ['192.168.1.1'],
        );

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '192.168.1.1']);
        $request = $request->withHeader('X-Forwarded-For', '10.0.0.1, 192.168.1.1');

        $middleware($request, $this->createHandler());
    }

    #[Test]
    public function ignoresForwardedForFromUntrustedProxy(): void
    {
        $storage = $this->createMock(RateLimitStorageInterface::class);
        $storage->expects($this->once())
            ->method('increment')
            ->with('1.2.3.4', 60)
            ->willReturn(['count' => 1, 'expires' => time() + 60]);

        $middleware = new RateLimit(
            maxRequests: 60,
            windowSeconds: 60,
            storage: $storage,
            trustedProxies: ['192.168.1.1'],
        );

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '1.2.3.4']);
        $request = $request->withHeader('X-Forwarded-For', '10.0.0.1');

        $middleware($request, $this->createHandler());
    }

    #[Test]
    public function fileStorageCleanupRemovesExpiredFiles(): void
    {
        $storage = new RateLimitFileStorage($this->storagePath);

        // Create an expired entry
        $file = $this->storagePath . '/' . md5('expired-client') . '.json';
        file_put_contents($file, json_encode(['count' => 5, 'expires' => time() - 100]));

        // Create a valid entry
        $validFile = $this->storagePath . '/' . md5('valid-client') . '.json';
        file_put_contents($validFile, json_encode(['count' => 2, 'expires' => time() + 100]));

        $removed = $storage->cleanup();

        $this->assertSame(1, $removed);
        $this->assertFileDoesNotExist($file);
        $this->assertFileExists($validFile);
    }

    #[Test]
    public function fileStorageIsAtomicWithLocking(): void
    {
        $storage = new RateLimitFileStorage($this->storagePath);

        $result1 = $storage->increment('client-a', 60);
        $result2 = $storage->increment('client-a', 60);
        $result3 = $storage->increment('client-a', 60);

        $this->assertSame(1, $result1['count']);
        $this->assertSame(2, $result2['count']);
        $this->assertSame(3, $result3['count']);
        // All should share the same expiry window
        $this->assertSame($result1['expires'], $result2['expires']);
    }

    #[Test]
    public function fileStorageResetsAfterWindowExpires(): void
    {
        $storage = new RateLimitFileStorage($this->storagePath);

        // Manually write an expired entry
        $file = $this->storagePath . '/' . md5('client-b') . '.json';
        file_put_contents($file, json_encode(['count' => 99, 'expires' => time() - 1]));

        $result = $storage->increment('client-b', 60);

        $this->assertSame(1, $result['count']);
    }
}
