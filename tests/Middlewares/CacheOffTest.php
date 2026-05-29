<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\CacheOff;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class CacheOffTest extends TestCase
{
    private CacheOff $middleware;
    private ServerRequestInterface $request;
    private RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        $this->middleware = new CacheOff();
        $this->request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = (new ResponseFactory())->createResponse();
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->handler->method('handle')->willReturn($response);
    }

    #[Test]
    public function setsCacheControlHeader(): void
    {
        $response = ($this->middleware)($this->request, $this->handler);

        $this->assertTrue($response->hasHeader('Cache-Control'));
        $cacheControl = $response->getHeaderLine('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
    }

    #[Test]
    public function setsPragmaHeader(): void
    {
        $response = ($this->middleware)($this->request, $this->handler);

        $this->assertSame('no-cache', $response->getHeaderLine('Pragma'));
    }

    #[Test]
    public function setsExpiresHeader(): void
    {
        $response = ($this->middleware)($this->request, $this->handler);

        $this->assertSame('Thu, 1 Jan 1970 00:00:00 GMT', $response->getHeaderLine('Expires'));
    }

    #[Test]
    public function returnsResponseInterface(): void
    {
        $response = ($this->middleware)($this->request, $this->handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function preservesExistingResponseBody(): void
    {
        $responseWithBody = (new ResponseFactory())->createResponse();
        $responseWithBody->getBody()->write('existing content');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($responseWithBody);

        $response = ($this->middleware)($this->request, $handler);

        $this->assertSame('existing content', (string)$response->getBody());
    }
}
