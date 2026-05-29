<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\LogEntry;
use Simsoft\Slim\Middlewares\Logging;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class LoggingTest extends TestCase
{
    private function createHandler(string $body = 'response body'): RequestHandlerInterface
    {
        $response = (new ResponseFactory())->createResponse();
        $response->getBody()->write($body);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    private function createRequest(string $method = 'GET', string $uri = 'https://example.com/test?q=hello'): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri, ['REMOTE_ADDR' => '192.168.1.100']);
        return $request->withQueryParams(['q' => 'hello']);
    }

    #[Test]
    public function recorderReceivesLogEntry(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest(), $this->createHandler());

        $this->assertInstanceOf(LogEntry::class, $captured);
    }

    #[Test]
    public function logEntryContainsMethod(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest('POST'), $this->createHandler());

        $this->assertSame('POST', $captured->method);
    }

    #[Test]
    public function logEntryContainsUri(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest('GET', 'https://example.com/users?page=1'), $this->createHandler());

        $this->assertStringContainsString('/users', $captured->uri);
        $this->assertSame('/users', $captured->path);
    }

    #[Test]
    public function logEntryContainsIp(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest(), $this->createHandler());

        $this->assertSame('192.168.1.100', $captured->ip);
    }

    #[Test]
    public function logEntryContainsQueryParams(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest(), $this->createHandler());

        $this->assertSame(['q' => 'hello'], $captured->queryParams);
    }

    #[Test]
    public function logEntryContainsStatus(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest(), $this->createHandler());

        $this->assertSame(200, $captured->status);
    }

    #[Test]
    public function logEntryContainsDuration(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest(), $this->createHandler());

        $this->assertGreaterThanOrEqual(0.0, $captured->durationMs);
        $this->assertIsFloat($captured->durationMs);
    }

    #[Test]
    public function logEntryContainsResponseBody(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest(), $this->createHandler('hello world'));

        $this->assertSame('hello world', $captured->responseBody);
    }

    #[Test]
    public function responseBodyCanBeDisabled(): void
    {
        $captured = null;
        $middleware = new Logging(
            function (LogEntry $entry) use (&$captured) {
                $captured = $entry;
            },
            logResponseBody: false,
        );

        $middleware($this->createRequest(), $this->createHandler('secret'));

        $this->assertNull($captured->responseBody);
    }

    #[Test]
    public function responseBodyIsTruncated(): void
    {
        $captured = null;
        $middleware = new Logging(
            function (LogEntry $entry) use (&$captured) {
                $captured = $entry;
            },
            maxBodyLength: 5,
        );

        $middleware($this->createRequest(), $this->createHandler('hello world'));

        $this->assertSame('hello', $captured->responseBody);
    }

    #[Test]
    public function logEntryContainsResponseSize(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest(), $this->createHandler('12345'));

        $this->assertSame(5, $captured->responseSize);
    }

    #[Test]
    public function logEntryContainsTimestamp(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest(), $this->createHandler());

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $captured->timestamp);
    }

    #[Test]
    public function logEntryContainsRequestId(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest(), $this->createHandler());

        $this->assertNotEmpty($captured->requestId);
        $this->assertSame(16, strlen($captured->requestId)); // bin2hex(8 bytes)
    }

    #[Test]
    public function logEntryUsesExistingRequestId(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $request = $this->createRequest()->withHeader('X-Request-ID', 'my-correlation-id');
        $middleware($request, $this->createHandler());

        $this->assertSame('my-correlation-id', $captured->requestId);
    }

    #[Test]
    public function logEntryContainsSchemeHostPort(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest('GET', 'https://example.com:8443/path'), $this->createHandler());

        $this->assertSame('https', $captured->scheme);
        $this->assertSame('example.com', $captured->host);
        $this->assertSame(8443, $captured->port);
    }

    #[Test]
    public function logEntryDetectsXhr(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $request = $this->createRequest()->withHeader('X-Requested-With', 'XMLHttpRequest');
        $middleware($request, $this->createHandler());

        $this->assertTrue($captured->isXhr);
    }

    #[Test]
    public function logEntryDetectsNonXhr(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest(), $this->createHandler());

        $this->assertFalse($captured->isXhr);
    }

    #[Test]
    public function logEntryContainsUserAgent(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $request = $this->createRequest()->withHeader('User-Agent', 'MyApp/1.0');
        $middleware($request, $this->createHandler());

        $this->assertSame('MyApp/1.0', $captured->userAgent);
    }

    #[Test]
    public function logEntryContainsUserFromAttribute(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $request = $this->createRequest()->withAttribute('user', ['id' => 1, 'name' => 'John']);
        $middleware($request, $this->createHandler());

        $this->assertSame(['id' => 1, 'name' => 'John'], $captured->user);
    }

    #[Test]
    public function logEntryUserIsNullWhenNotSet(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest(), $this->createHandler());

        $this->assertNull($captured->user);
    }

    #[Test]
    public function logEntryContainsAttributes(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $request = $this->createRequest()
            ->withAttribute('user', ['id' => 1])
            ->withAttribute('tenant', 'acme');
        $middleware($request, $this->createHandler());

        $this->assertArrayHasKey('user', $captured->attributes);
        $this->assertArrayHasKey('tenant', $captured->attributes);
        $this->assertSame('acme', $captured->attributes['tenant']);
    }

    #[Test]
    public function logEntryContainsMemoryUsage(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest(), $this->createHandler());

        $this->assertGreaterThan(0, $captured->memoryUsage);
    }

    #[Test]
    public function logEntryContainsPid(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest(), $this->createHandler());

        $this->assertGreaterThan(0, $captured->pid);
    }

    #[Test]
    public function logEntryToArrayReturnsAllFields(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $middleware($this->createRequest(), $this->createHandler());

        $array = $captured->toArray();
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertArrayHasKey('request_id', $array);
        $this->assertArrayHasKey('method', $array);
        $this->assertArrayHasKey('uri', $array);
        $this->assertArrayHasKey('ip', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('duration_ms', $array);
        $this->assertArrayHasKey('memory_usage', $array);
        $this->assertArrayHasKey('scheme', $array);
        $this->assertArrayHasKey('host', $array);
        $this->assertArrayHasKey('path', $array);
        $this->assertArrayHasKey('is_xhr', $array);
        $this->assertArrayHasKey('pid', $array);
        $this->assertArrayHasKey('response_size', $array);
        $this->assertArrayHasKey('response_headers', $array);
        $this->assertArrayHasKey('attributes', $array);
    }

    #[Test]
    public function responseBodyStreamIsRewound(): void
    {
        $response = (new ResponseFactory())->createResponse();
        $response->getBody()->write('important data');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $middleware = new Logging(function (LogEntry $entry) {
        });

        $result = $middleware($this->createRequest(), $handler);

        // Body should still be readable after logging
        $this->assertSame('important data', (string)$result->getBody());
    }

    #[Test]
    public function requestHeadersIncludedByDefault(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $request = $this->createRequest()->withHeader('X-Custom', 'test-value');
        $middleware($request, $this->createHandler());

        $this->assertArrayHasKey('X-Custom', $captured->requestHeaders);
        $this->assertSame('test-value', $captured->requestHeaders['X-Custom']);
    }

    #[Test]
    public function requestHeadersCanBeDisabled(): void
    {
        $captured = null;
        $middleware = new Logging(
            function (LogEntry $entry) use (&$captured) {
                $captured = $entry;
            },
            logRequestHeaders: false,
        );

        $request = $this->createRequest()->withHeader('X-Custom', 'test-value');
        $middleware($request, $this->createHandler());

        $this->assertEmpty($captured->requestHeaders);
    }

    #[Test]
    public function responseHeadersIncludedByDefault(): void
    {
        $captured = null;
        $middleware = new Logging(function (LogEntry $entry) use (&$captured) {
            $captured = $entry;
        });

        $response = (new ResponseFactory())->createResponse()->withHeader('X-App', 'slim');
        $response->getBody()->write('body');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $middleware($this->createRequest(), $handler);

        $this->assertArrayHasKey('X-App', $captured->responseHeaders);
    }

    #[Test]
    public function responseHeadersCanBeDisabled(): void
    {
        $captured = null;
        $middleware = new Logging(
            function (LogEntry $entry) use (&$captured) {
                $captured = $entry;
            },
            logResponseHeaders: false,
        );

        $middleware($this->createRequest(), $this->createHandler());

        $this->assertEmpty($captured->responseHeaders);
    }
}
