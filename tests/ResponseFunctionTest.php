<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Slim\Response;
use Slim\Psr7\Factory\ResponseFactory;

use function Simsoft\Slim\response;

class ResponseFunctionTest extends TestCase
{
    protected function setUp(): void
    {
        Response::$response = (new ResponseFactory())->createResponse();
    }

    #[Test]
    public function responseFunctionReturnsResponseInstance(): void
    {
        $result = response();
        $this->assertInstanceOf(Response::class, $result);
    }

    #[Test]
    public function responseFunctionWithStringContent(): void
    {
        response('Hello World');
        $this->assertSame('Hello World', (string)Response::$response->getBody());
    }

    #[Test]
    public function responseFunctionWithArrayContent(): void
    {
        response(['key' => 'value']);

        $body = (string)Response::$response->getBody();
        $this->assertJson($body);
        $this->assertSame(['key' => 'value'], json_decode($body, true));
    }

    #[Test]
    public function responseFunctionWithStatusCode(): void
    {
        response('Not Found', 404);

        $this->assertSame('Not Found', (string)Response::$response->getBody());
        $this->assertSame(404, Response::$response->getStatusCode());
    }

    #[Test]
    public function responseFunctionWithNullContentAndStatusCode(): void
    {
        response(null, 204);

        $this->assertSame('', (string)Response::$response->getBody());
        $this->assertSame(204, Response::$response->getStatusCode());
    }

    #[Test]
    public function responseFunctionWithNoArguments(): void
    {
        $result = response();

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame('', (string)Response::$response->getBody());
        $this->assertSame(200, Response::$response->getStatusCode());
    }
}
