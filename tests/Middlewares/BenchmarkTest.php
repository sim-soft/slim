<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\Benchmark;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class BenchmarkTest extends TestCase
{
    private function createHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn((new ResponseFactory())->createResponse());
        return $handler;
    }

    #[Test]
    public function addsResponseTimeHeader(): void
    {
        $middleware = new Benchmark();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());

        $this->assertTrue($response->hasHeader('X-Response-Time'));
        $this->assertMatchesRegularExpression('/^\d+\.\d+ms$/', $response->getHeaderLine('X-Response-Time'));
    }

    #[Test]
    public function addsMemoryPeakHeader(): void
    {
        $middleware = new Benchmark();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());

        $this->assertTrue($response->hasHeader('X-Memory-Peak'));
        $this->assertMatchesRegularExpression('/^\d+\.\d+(MB|KB)$/', $response->getHeaderLine('X-Memory-Peak'));
    }

    #[Test]
    public function memoryHeaderCanBeDisabled(): void
    {
        $middleware = new Benchmark(includeMemory: false);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());

        $this->assertTrue($response->hasHeader('X-Response-Time'));
        $this->assertFalse($response->hasHeader('X-Memory-Peak'));
    }

    #[Test]
    public function customHeaderNames(): void
    {
        $middleware = new Benchmark(timeHeader: 'X-Time', memoryHeader: 'X-Mem');
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());

        $this->assertTrue($response->hasHeader('X-Time'));
        $this->assertTrue($response->hasHeader('X-Mem'));
        $this->assertFalse($response->hasHeader('X-Response-Time'));
    }

    #[Test]
    public function preservesResponseBody(): void
    {
        $responseWithBody = (new ResponseFactory())->createResponse();
        $responseWithBody->getBody()->write('hello');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($responseWithBody);

        $middleware = new Benchmark();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $handler);
        $this->assertSame('hello', (string)$response->getBody());
    }

    #[Test]
    public function returnsResponseInterface(): void
    {
        $middleware = new Benchmark();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
