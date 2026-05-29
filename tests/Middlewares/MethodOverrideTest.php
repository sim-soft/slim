<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\MethodOverride;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Psr\Http\Message\ResponseInterface;

class MethodOverrideTest extends TestCase
{
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
    public function overridesPostToDeleteViaBody(): void
    {
        $middleware = new MethodOverride();
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withParsedBody(['_METHOD' => 'DELETE']);
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('DELETE', $handler->capturedRequest->getMethod());
    }

    #[Test]
    public function overridesPostToPutViaBody(): void
    {
        $middleware = new MethodOverride();
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withParsedBody(['_METHOD' => 'PUT']);
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('PUT', $handler->capturedRequest->getMethod());
    }

    #[Test]
    public function overridesPostToPatchViaBody(): void
    {
        $middleware = new MethodOverride();
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withParsedBody(['_METHOD' => 'PATCH']);
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('PATCH', $handler->capturedRequest->getMethod());
    }

    #[Test]
    public function overridesViaHeader(): void
    {
        $middleware = new MethodOverride();
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withHeader('X-Http-Method-Override', 'DELETE');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('DELETE', $handler->capturedRequest->getMethod());
    }

    #[Test]
    public function headerTakesPriorityOverBody(): void
    {
        $middleware = new MethodOverride();
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withHeader('X-Http-Method-Override', 'PUT')
            ->withParsedBody(['_METHOD' => 'DELETE']);
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('PUT', $handler->capturedRequest->getMethod());
    }

    #[Test]
    public function ignoresGetRequests(): void
    {
        $middleware = new MethodOverride();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withHeader('X-Http-Method-Override', 'DELETE');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('GET', $handler->capturedRequest->getMethod());
    }

    #[Test]
    public function ignoresInvalidMethod(): void
    {
        $middleware = new MethodOverride();
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withParsedBody(['_METHOD' => 'INVALID']);
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('POST', $handler->capturedRequest->getMethod());
    }

    #[Test]
    public function ignoresGetAsOverride(): void
    {
        $middleware = new MethodOverride();
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withParsedBody(['_METHOD' => 'GET']);
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('POST', $handler->capturedRequest->getMethod());
    }

    #[Test]
    public function caseInsensitiveOverride(): void
    {
        $middleware = new MethodOverride();
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withParsedBody(['_METHOD' => 'delete']);
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('DELETE', $handler->capturedRequest->getMethod());
    }

    #[Test]
    public function customFieldName(): void
    {
        $middleware = new MethodOverride(fieldName: '_method');
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withParsedBody(['_method' => 'PUT']);
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('PUT', $handler->capturedRequest->getMethod());
    }

    #[Test]
    public function customHeaderName(): void
    {
        $middleware = new MethodOverride(headerName: 'X-Method');
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withHeader('X-Method', 'DELETE');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('DELETE', $handler->capturedRequest->getMethod());
    }

    #[Test]
    public function noOverridePassesThrough(): void
    {
        $middleware = new MethodOverride();
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withParsedBody(['name' => 'John']);
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('POST', $handler->capturedRequest->getMethod());
    }
}
