<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\Csrf;
use Slim\Exception\HttpException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        // Simulate active session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function createHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn((new ResponseFactory())->createResponse());
        return $handler;
    }

    #[Test]
    public function getRequestsPassWithoutToken(): void
    {
        $csrf = new Csrf();
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $csrf($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function generateTokenReturns64CharHex(): void
    {
        $csrf = new Csrf();
        $token = $csrf->generateToken();

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    #[Test]
    public function generateTokenStoresInSession(): void
    {
        $csrf = new Csrf();
        $token = $csrf->generateToken();

        $pool = $_SESSION['_csrf_tokens'] ?? [];
        $this->assertArrayHasKey($token, $pool);
    }

    #[Test]
    public function getTokenReturnsSameTokenWithinRequest(): void
    {
        $csrf = new Csrf();
        $token1 = $csrf->getToken();
        $token2 = $csrf->getToken();

        $this->assertSame($token1, $token2);
    }

    #[Test]
    public function getTokenFieldReturnsHiddenInput(): void
    {
        $csrf = new Csrf();
        $field = $csrf->getTokenField();

        $this->assertStringContainsString('<input type="hidden"', $field);
        $this->assertStringContainsString('name="_csrf_token"', $field);
        $this->assertStringContainsString('value="', $field);
    }

    #[Test]
    public function postWithValidTokenInBodyPasses(): void
    {
        $csrf = new Csrf();
        $token = $csrf->generateToken();

        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withParsedBody(['_csrf_token' => $token]);

        $response = $csrf($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function postWithValidTokenInHeaderPasses(): void
    {
        $csrf = new Csrf();
        $token = $csrf->generateToken();

        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withHeader('X-CSRF-Token', $token);

        $response = $csrf($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function postWithInvalidTokenThrows403(): void
    {
        $csrf = new Csrf();
        $csrf->generateToken();

        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withParsedBody(['_csrf_token' => 'invalid-token']);

        $this->expectException(HttpException::class);
        $csrf($request, $this->createHandler());
    }

    #[Test]
    public function postWithMissingTokenThrows403(): void
    {
        $csrf = new Csrf();

        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withParsedBody([]);

        $this->expectException(HttpException::class);
        $csrf($request, $this->createHandler());
    }

    #[Test]
    public function tokenIsConsumedAfterUse(): void
    {
        $csrf = new Csrf();
        $token = $csrf->generateToken();

        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withParsedBody(['_csrf_token' => $token]);

        // First use succeeds
        $csrf($request, $this->createHandler());

        // Second use with same token fails
        $this->expectException(HttpException::class);
        $csrf($request, $this->createHandler());
    }

    #[Test]
    public function multipleTokensCanCoexist(): void
    {
        $csrf = new Csrf();
        $token1 = $csrf->generateToken();
        $token2 = $csrf->generateToken();

        $this->assertNotSame($token1, $token2);

        // Both should be valid
        $request2 = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request2 = $request2->withParsedBody(['_csrf_token' => $token2]);
        $csrf($request2, $this->createHandler());

        // token1 should still work (not consumed)
        $request1 = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request1 = $request1->withParsedBody(['_csrf_token' => $token1]);
        $response = $csrf($request1, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function poolSizeIsLimited(): void
    {
        $csrf = new Csrf(maxTokens: 3);

        $csrf->generateToken();
        $csrf->generateToken();
        $csrf->generateToken();
        $csrf->generateToken(); // Should evict the first

        $pool = $_SESSION['_csrf_tokens'];
        $this->assertCount(3, $pool);
    }

    #[Test]
    public function putDeletePatchRequireToken(): void
    {
        $csrf = new Csrf();

        foreach (['PUT', 'DELETE', 'PATCH'] as $method) {
            $_SESSION = [];
            $request = (new ServerRequestFactory())->createServerRequest($method, 'https://example.com');
            $request = $request->withParsedBody([]);

            try {
                $csrf($request, $this->createHandler());
                $this->fail("Expected HttpException for $method without token");
            } catch (HttpException $ex) {
                $this->assertSame(403, $ex->getCode());
            }
        }
    }

    #[Test]
    public function customFieldAndHeaderNames(): void
    {
        $csrf = new Csrf(fieldName: 'my_token', headerName: 'X-My-Token');
        $token = $csrf->generateToken();

        $this->assertSame('my_token', $csrf->getFieldName());
        $this->assertSame('X-My-Token', $csrf->getHeaderName());

        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com');
        $request = $request->withHeader('X-My-Token', $token);

        $response = $csrf($request, $this->createHandler());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function headAndOptionsRequestsPassWithoutToken(): void
    {
        $csrf = new Csrf();

        foreach (['HEAD', 'OPTIONS'] as $method) {
            $request = (new ServerRequestFactory())->createServerRequest($method, 'https://example.com');
            $response = $csrf($request, $this->createHandler());
            $this->assertInstanceOf(ResponseInterface::class, $response);
        }
    }
}
