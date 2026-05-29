<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\SecurityHeaders;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class SecurityHeadersTest extends TestCase
{
    private function createHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn((new ResponseFactory())->createResponse());
        return $handler;
    }

    #[Test]
    public function addsDefaultHeaders(): void
    {
        $middleware = new SecurityHeaders();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());

        $this->assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('1; mode=block', $response->getHeaderLine('X-XSS-Protection'));
        $this->assertSame('strict-origin-when-cross-origin', $response->getHeaderLine('Referrer-Policy'));
        $this->assertStringContainsString('geolocation=()', $response->getHeaderLine('Permissions-Policy'));
    }

    #[Test]
    public function customHeadersOverrideDefaults(): void
    {
        $middleware = new SecurityHeaders(['X-Custom' => 'value']);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());

        $this->assertSame('value', $response->getHeaderLine('X-Custom'));
        $this->assertFalse($response->hasHeader('X-Frame-Options'));
    }

    #[Test]
    public function withHstsAddsStrictTransportSecurity(): void
    {
        $middleware = (new SecurityHeaders())->withHsts();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());

        $hsts = $response->getHeaderLine('Strict-Transport-Security');
        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
    }

    #[Test]
    public function withHstsCustomMaxAge(): void
    {
        $middleware = (new SecurityHeaders())->withHsts(maxAge: 86400, includeSubDomains: false);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());

        $hsts = $response->getHeaderLine('Strict-Transport-Security');
        $this->assertSame('max-age=86400', $hsts);
    }

    #[Test]
    public function withCspAddsContentSecurityPolicy(): void
    {
        $middleware = (new SecurityHeaders())->withCsp("default-src 'self'");
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());

        $this->assertSame("default-src 'self'", $response->getHeaderLine('Content-Security-Policy'));
    }

    #[Test]
    public function withHstsReturnsNewInstance(): void
    {
        $original = new SecurityHeaders();
        $withHsts = $original->withHsts();

        $this->assertNotSame($original, $withHsts);
    }

    #[Test]
    public function withCspReturnsNewInstance(): void
    {
        $original = new SecurityHeaders();
        $withCsp = $original->withCsp("default-src 'self'");

        $this->assertNotSame($original, $withCsp);
    }

    #[Test]
    public function chainingHstsAndCsp(): void
    {
        $middleware = (new SecurityHeaders())->withHsts()->withCsp("script-src 'self'");
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $middleware($request, $this->createHandler());

        $this->assertTrue($response->hasHeader('Strict-Transport-Security'));
        $this->assertTrue($response->hasHeader('Content-Security-Policy'));
        $this->assertTrue($response->hasHeader('X-Frame-Options'));
    }
}
