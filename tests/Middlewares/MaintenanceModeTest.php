<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\MaintenanceMode;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class MaintenanceModeTest extends TestCase
{
    private function createHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn((new ResponseFactory())->createResponse());
        return $handler;
    }

    #[Test]
    public function disabledPassesThrough(): void
    {
        $middleware = new MaintenanceMode(enabled: false);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function enabledReturns503(): void
    {
        $middleware = new MaintenanceMode(enabled: true);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());

        $this->assertSame(503, $response->getStatusCode());
    }

    #[Test]
    public function retryAfterHeaderIsSent(): void
    {
        // The constructor has always accepted $retryAfter; it was never
        // actually emitted, so clients had nothing telling them when to return.
        $middleware = new MaintenanceMode(enabled: true, retryAfter: 120);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());

        $this->assertSame('120', $response->getHeaderLine('Retry-After'));
    }

    #[Test]
    public function retryAfterDefaultsToOneHour(): void
    {
        $middleware = new MaintenanceMode(enabled: true);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());

        $this->assertSame('3600', $response->getHeaderLine('Retry-After'));
    }

    #[Test]
    public function customMessage(): void
    {
        $middleware = new MaintenanceMode(enabled: true, message: 'Down for upgrade');
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());

        $response->getBody()->rewind();
        $this->assertSame('Down for upgrade', $response->getBody()->getContents());
    }

    #[Test]
    public function allowedIpBypasses(): void
    {
        $middleware = new MaintenanceMode(
            enabled: true,
            allowedIps: ['127.0.0.1'],
        );
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '127.0.0.1']);

        $response = $middleware($request, $this->createHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getHeaderLine('Retry-After'));
    }

    #[Test]
    public function nonAllowedIpBlocked(): void
    {
        $middleware = new MaintenanceMode(
            enabled: true,
            allowedIps: ['127.0.0.1'],
        );
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '192.168.1.50']);

        $response = $middleware($request, $this->createHandler());

        $this->assertSame(503, $response->getStatusCode());
    }

    #[Test]
    public function emptyAllowedIpsBlocksEveryone(): void
    {
        $middleware = new MaintenanceMode(enabled: true, allowedIps: []);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '127.0.0.1']);

        $response = $middleware($request, $this->createHandler());

        $this->assertSame(503, $response->getStatusCode());
    }
}
