<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\ContentNegotiation;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Psr\Http\Message\ResponseInterface;

class ContentNegotiationTest extends TestCase
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
    public function defaultsToJsonWhenNoAcceptHeader(): void
    {
        $middleware = new ContentNegotiation();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('json', $handler->capturedRequest->getAttribute('format'));
    }

    #[Test]
    public function defaultsToJsonForWildcard(): void
    {
        $middleware = new ContentNegotiation();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withHeader('Accept', '*/*');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('json', $handler->capturedRequest->getAttribute('format'));
    }

    #[Test]
    public function resolvesJson(): void
    {
        $middleware = new ContentNegotiation();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withHeader('Accept', 'application/json');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('json', $handler->capturedRequest->getAttribute('format'));
    }

    #[Test]
    public function resolvesHtml(): void
    {
        $middleware = new ContentNegotiation();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withHeader('Accept', 'text/html');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('html', $handler->capturedRequest->getAttribute('format'));
    }

    #[Test]
    public function resolvesXml(): void
    {
        $middleware = new ContentNegotiation();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withHeader('Accept', 'application/xml');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('xml', $handler->capturedRequest->getAttribute('format'));
    }

    #[Test]
    public function resolvesTextXmlAsXml(): void
    {
        $middleware = new ContentNegotiation();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withHeader('Accept', 'text/xml');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('xml', $handler->capturedRequest->getAttribute('format'));
    }

    #[Test]
    public function resolvesPlainText(): void
    {
        $middleware = new ContentNegotiation();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withHeader('Accept', 'text/plain');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('text', $handler->capturedRequest->getAttribute('format'));
    }

    #[Test]
    public function respectsQualityValues(): void
    {
        $middleware = new ContentNegotiation();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withHeader('Accept', 'text/html;q=0.9, application/json;q=1.0');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('json', $handler->capturedRequest->getAttribute('format'));
    }

    #[Test]
    public function customFormats(): void
    {
        $middleware = new ContentNegotiation(formats: ['text/csv' => 'csv', 'application/json' => 'json']);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withHeader('Accept', 'text/csv');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('csv', $handler->capturedRequest->getAttribute('format'));
    }

    #[Test]
    public function customDefaultFormat(): void
    {
        $middleware = new ContentNegotiation(defaultFormat: 'html');
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withHeader('Accept', 'application/unknown');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('html', $handler->capturedRequest->getAttribute('format'));
    }

    #[Test]
    public function customAttributeName(): void
    {
        $middleware = new ContentNegotiation(attribute: 'responseFormat');
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withHeader('Accept', 'application/json');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        $this->assertSame('json', $handler->capturedRequest->getAttribute('responseFormat'));
        $this->assertNull($handler->capturedRequest->getAttribute('format'));
    }

    #[Test]
    public function wildcardSubtypeMatches(): void
    {
        $middleware = new ContentNegotiation();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withHeader('Accept', 'text/*');
        $handler = $this->createCapturingHandler();

        $middleware($request, $handler);

        // text/* should match text/html first
        $this->assertSame('html', $handler->capturedRequest->getAttribute('format'));
    }
}
