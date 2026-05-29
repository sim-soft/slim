<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Slim\Request;
use Slim\Psr7\Factory\ServerRequestFactory;

use function Simsoft\Slim\request;

class RequestTest extends TestCase
{
    protected function setUp(): void
    {
        Request::$request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com/test');
    }

    #[Test]
    public function getInstanceReturnsRequestObject(): void
    {
        $instance = Request::getInstance();
        $this->assertInstanceOf(Request::class, $instance);
    }

    #[Test]
    public function requestFunctionReturnsRequestInstance(): void
    {
        $result = request();
        $this->assertInstanceOf(Request::class, $result);
    }

    #[Test]
    public function isXhrReturnsFalseForNormalRequest(): void
    {
        $request = Request::getInstance();
        $this->assertFalse($request->isXHR());
    }

    #[Test]
    public function isXhrReturnsTrueForXhrRequest(): void
    {
        Request::$request = Request::$request->withHeader('X-Requested-With', 'XMLHttpRequest');
        $request = Request::getInstance();
        $this->assertTrue($request->isXHR());
    }

    #[Test]
    public function isMethodReturnsTrueForMatchingMethod(): void
    {
        $request = Request::getInstance();
        $this->assertTrue($request->isMethod('get'));
        $this->assertTrue($request->isMethod('GET'));
    }

    #[Test]
    public function isMethodReturnsFalseForNonMatchingMethod(): void
    {
        $request = Request::getInstance();
        $this->assertFalse($request->isMethod('post'));
        $this->assertFalse($request->isMethod('PUT'));
    }

    #[Test]
    public function headerReturnsHeaderValue(): void
    {
        Request::$request = Request::$request->withHeader('Content-Type', 'application/json');
        $request = Request::getInstance();

        $this->assertSame('application/json', $request->header('Content-Type'));
    }

    #[Test]
    public function headerReturnsDefaultWhenMissing(): void
    {
        $request = Request::getInstance();
        $this->assertSame('default-value', $request->header('X-Missing', 'default-value'));
    }

    #[Test]
    public function headerReturnsEmptyStringWhenMissingAndNoDefault(): void
    {
        $request = Request::getInstance();
        $this->assertSame('', $request->header('X-Missing'));
    }

    #[Test]
    public function getBearerTokenExtractsToken(): void
    {
        Request::$request = Request::$request->withHeader('Authorization', 'Bearer abc123token');
        $request = Request::getInstance();

        $this->assertSame('abc123token', $request->getBearerToken());
    }

    #[Test]
    public function getBearerTokenReturnsEmptyWhenNoAuthHeader(): void
    {
        $request = Request::getInstance();
        $this->assertSame('', $request->getBearerToken());
    }

    #[Test]
    public function magicCallDelegatesToPsr7Request(): void
    {
        $request = Request::getInstance();
        $method = $request->getMethod();

        $this->assertSame('GET', $method);
    }

    #[Test]
    public function magicCallWithNonExistentMethodReturnsNull(): void
    {
        $request = Request::getInstance();
        $result = $request->nonExistentMethod();

        $this->assertNull($result);
    }

    #[Test]
    public function magicCallUpdatesStaticRequestForMutatingMethods(): void
    {
        $request = Request::getInstance();
        $request->withMethod('POST');

        $this->assertSame('POST', Request::$request->getMethod());
    }

    #[Test]
    public function getQueryParamsReturnsEmptyArrayByDefault(): void
    {
        $request = Request::getInstance();
        $this->assertSame([], $request->getQueryParams());
    }

    #[Test]
    public function getQueryParamsReturnsParams(): void
    {
        Request::$request = Request::$request->withQueryParams(['page' => '1', 'limit' => '10']);
        $request = Request::getInstance();

        $this->assertSame(['page' => '1', 'limit' => '10'], $request->getQueryParams());
    }

    #[Test]
    public function notFoundThrowsHttpNotFoundException(): void
    {
        $this->expectException(\Slim\Exception\HttpNotFoundException::class);

        $request = Request::getInstance();
        $request->notFound();
    }

    #[Test]
    public function notFoundWithCustomMessage(): void
    {
        $this->expectException(\Slim\Exception\HttpNotFoundException::class);
        $this->expectExceptionMessage('Resource not available');

        $request = Request::getInstance();
        $request->notFound('Resource not available');
    }

    #[Test]
    public function allowedMethodsThrowsHttpMethodNotAllowedException(): void
    {
        $this->expectException(\Slim\Exception\HttpMethodNotAllowedException::class);

        $request = Request::getInstance();
        $request->allowedMethods(['POST', 'PUT']);
    }

    #[Test]
    public function isMethodWithPostRequest(): void
    {
        Request::$request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com/test');
        $request = Request::getInstance();

        $this->assertTrue($request->isMethod('post'));
        $this->assertTrue($request->isMethod('POST'));
        $this->assertFalse($request->isMethod('get'));
    }
}
