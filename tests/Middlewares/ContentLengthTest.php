<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\ContentLength;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class ContentLengthTest extends TestCase
{
    #[Test]
    public function addsContentLengthHeader(): void
    {
        $response = (new ResponseFactory())->createResponse();
        $response->getBody()->write('hello');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $middleware = new ContentLength();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $result = $middleware($request, $handler);

        $this->assertSame('5', $result->getHeaderLine('Content-Length'));
    }

    #[Test]
    public function doesNotOverrideExistingHeader(): void
    {
        $response = (new ResponseFactory())->createResponse();
        $response->getBody()->write('hello');
        $response = $response->withHeader('Content-Length', '99');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $middleware = new ContentLength();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $result = $middleware($request, $handler);

        $this->assertSame('99', $result->getHeaderLine('Content-Length'));
    }

    #[Test]
    public function emptyBodySetsZero(): void
    {
        $response = (new ResponseFactory())->createResponse();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $middleware = new ContentLength();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $result = $middleware($request, $handler);

        $this->assertSame('0', $result->getHeaderLine('Content-Length'));
    }
}
