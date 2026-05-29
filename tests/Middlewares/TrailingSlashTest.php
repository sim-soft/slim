<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\TrailingSlash;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class TrailingSlashTest extends TestCase
{
    private function createHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn((new ResponseFactory())->createResponse());
        return $handler;
    }

    private function createCapturingHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $capturedRequest = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->capturedRequest = $request;
                return (new ResponseFactory())->createResponse();
            }
        };
    }

    #[Test]
    public function removesTrailingSlashWithRedirect(): void
    {
        $middleware = new TrailingSlash();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com/users/');

        $response = $middleware($request, $this->createHandler());

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('https://example.com/users', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function noRedirectWhenNoTrailingSlash(): void
    {
        $middleware = new TrailingSlash();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com/users');

        $response = $middleware($request, $this->createHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function rootPathIsNotModified(): void
    {
        $middleware = new TrailingSlash();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com/');

        $response = $middleware($request, $this->createHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function addsTrailingSlashWithRedirect(): void
    {
        $middleware = new TrailingSlash(add: true);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com/users');

        $response = $middleware($request, $this->createHandler());

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('https://example.com/users/', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function silentRewriteRemovesSlash(): void
    {
        $middleware = new TrailingSlash(redirect: false);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com/users/');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('/users', $handler->capturedRequest->getUri()->getPath());
    }

    #[Test]
    public function silentRewriteAddsSlash(): void
    {
        $middleware = new TrailingSlash(add: true, redirect: false);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com/users');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('/users/', $handler->capturedRequest->getUri()->getPath());
    }

    #[Test]
    public function alreadyCorrectNoAction(): void
    {
        $middleware = new TrailingSlash(add: true);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com/users/');

        $response = $middleware($request, $this->createHandler());

        $this->assertSame(200, $response->getStatusCode());
    }
}
