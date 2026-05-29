<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\MaintenanceMode;
use Slim\Exception\HttpException;
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
    }

    #[Test]
    public function enabledThrows503(): void
    {
        $middleware = new MaintenanceMode(enabled: true);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(503);

        $middleware($request, $this->createHandler());
    }

    #[Test]
    public function customMessage(): void
    {
        $middleware = new MaintenanceMode(enabled: true, message: 'Down for upgrade');
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        try {
            $middleware($request, $this->createHandler());
            $this->fail('Expected HttpException');
        } catch (HttpException $ex) {
            $this->assertSame('Down for upgrade', $ex->getMessage());
        }
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

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function nonAllowedIpBlocked(): void
    {
        $middleware = new MaintenanceMode(
            enabled: true,
            allowedIps: ['127.0.0.1'],
        );
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '192.168.1.50']);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(503);

        $middleware($request, $this->createHandler());
    }

    #[Test]
    public function emptyAllowedIpsBlocksEveryone(): void
    {
        $middleware = new MaintenanceMode(enabled: true, allowedIps: []);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '127.0.0.1']);

        $this->expectException(HttpException::class);
        $middleware($request, $this->createHandler());
    }
}
