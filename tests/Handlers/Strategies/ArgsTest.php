<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Handlers\Strategies;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Simsoft\Slim\Handlers\Strategies\Args;
use Simsoft\Slim\Request;
use Simsoft\Slim\Response;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class ArgsTest extends TestCase
{
    private Args $strategy;

    protected function setUp(): void
    {
        $this->strategy = new Args();
    }

    #[Test]
    public function invokeSetsStaticRequestAndResponse(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $this->strategy->__invoke(
            function () {
            },
            $request,
            $response,
            []
        );

        $this->assertSame($request, Request::$request);
    }

    #[Test]
    public function invokePassesRouteArgumentsToCallable(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $receivedArgs = [];
        $callable = function (string $name, string $id) use (&$receivedArgs) {
            $receivedArgs = ['name' => $name, 'id' => $id];
        };

        $this->strategy->__invoke(
            $callable,
            $request,
            $response,
            ['name' => 'john', 'id' => '42']
        );

        $this->assertSame(['name' => 'john', 'id' => '42'], $receivedArgs);
    }

    #[Test]
    public function invokeReturnsResponseWhenCallableReturnsNull(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $result = $this->strategy->__invoke(
            function () {
            },
            $request,
            $response,
            []
        );

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    #[Test]
    public function invokeHandlesStringReturnValue(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $result = $this->strategy->__invoke(
            function () {
                return 'Hello World';
            },
            $request,
            $response,
            []
        );

        $this->assertSame('Hello World', (string)$result->getBody());
    }

    #[Test]
    public function invokeHandlesArrayReturnValueAsJson(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $data = ['status' => 'ok', 'count' => 5];
        $result = $this->strategy->__invoke(
            function () use ($data) {
                return $data;
            },
            $request,
            $response,
            []
        );

        $body = (string)$result->getBody();
        $this->assertJson($body);
        $this->assertSame($data, json_decode($body, true));
        $this->assertSame('application/json', $result->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function invokeWithEmptyRouteArguments(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $called = false;
        $result = $this->strategy->__invoke(
            function () use (&$called) {
                $called = true;
                return 'no args';
            },
            $request,
            $response,
            []
        );

        $this->assertTrue($called);
        $this->assertSame('no args', (string)$result->getBody());
    }

    #[Test]
    public function invokeWithCallableReturningFalseReturnsResponse(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $result = $this->strategy->__invoke(
            function () {
                return false;
            },
            $request,
            $response,
            []
        );

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame('', (string)$result->getBody());
    }

    #[Test]
    public function invokeWithCallableReturningZeroReturnsResponse(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $result = $this->strategy->__invoke(
            function () {
                return 0;
            },
            $request,
            $response,
            []
        );

        // 0 is falsy, so it should return the response without writing
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    #[Test]
    public function invokeWithCallableReturningEmptyStringReturnsResponse(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $result = $this->strategy->__invoke(
            function () {
                return '';
            },
            $request,
            $response,
            []
        );

        // Empty string is falsy
        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame('', (string)$result->getBody());
    }

    #[Test]
    public function invokeJsonWithUnescapedSlashes(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $result = $this->strategy->__invoke(
            function () {
                return ['url' => 'https://example.com/path/to/resource'];
            },
            $request,
            $response,
            []
        );

        $body = (string)$result->getBody();
        $this->assertStringContainsString('https://example.com/path/to/resource', $body);
        $this->assertStringNotContainsString('\\/', $body);
    }
}
