<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\IpFilter;
use Slim\Exception\HttpException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class IpFilterTest extends TestCase
{
    private function createHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn((new ResponseFactory())->createResponse());
        return $handler;
    }

    #[Test]
    public function whitelistAllowsListedIp(): void
    {
        $middleware = new IpFilter(ips: ['192.168.1.100'], whitelist: true);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '192.168.1.100']);

        $response = $middleware($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function whitelistBlocksUnlistedIp(): void
    {
        $middleware = new IpFilter(ips: ['192.168.1.100'], whitelist: true);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '10.0.0.1']);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $middleware($request, $this->createHandler());
    }

    #[Test]
    public function blacklistBlocksListedIp(): void
    {
        $middleware = new IpFilter(ips: ['1.2.3.4'], whitelist: false);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '1.2.3.4']);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $middleware($request, $this->createHandler());
    }

    #[Test]
    public function blacklistAllowsUnlistedIp(): void
    {
        $middleware = new IpFilter(ips: ['1.2.3.4'], whitelist: false);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '10.0.0.1']);

        $response = $middleware($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function customStatusCode(): void
    {
        $middleware = new IpFilter(ips: ['192.168.1.1'], whitelist: true, statusCode: 404);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '10.0.0.1']);

        try {
            $middleware($request, $this->createHandler());
            $this->fail('Expected HttpException');
        } catch (HttpException $ex) {
            $this->assertSame(404, $ex->getCode());
        }
    }

    #[Test]
    public function customMessage(): void
    {
        $middleware = new IpFilter(ips: ['192.168.1.1'], whitelist: true, message: 'Not found');
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '10.0.0.1']);

        try {
            $middleware($request, $this->createHandler());
            $this->fail('Expected HttpException');
        } catch (HttpException $ex) {
            $this->assertSame('Not found', $ex->getMessage());
        }
    }

    #[Test]
    public function multipleIpsInWhitelist(): void
    {
        $middleware = new IpFilter(ips: ['192.168.1.1', '192.168.1.2', '10.0.0.1']);

        $request1 = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '192.168.1.2']);
        $response = $middleware($request1, $this->createHandler());
        $this->assertInstanceOf(ResponseInterface::class, $response);

        $request2 = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '10.0.0.1']);
        $response = $middleware($request2, $this->createHandler());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function emptyWhitelistBlocksEveryone(): void
    {
        $middleware = new IpFilter(ips: [], whitelist: true);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '127.0.0.1']);

        $this->expectException(HttpException::class);
        $middleware($request, $this->createHandler());
    }

    #[Test]
    public function emptyBlacklistAllowsEveryone(): void
    {
        $middleware = new IpFilter(ips: [], whitelist: false);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com', ['REMOTE_ADDR' => '1.2.3.4']);

        $response = $middleware($request, $this->createHandler());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
