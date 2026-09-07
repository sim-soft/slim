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

    #[Test]
    public function responseFunctionPreservesZeroStringContent(): void
    {
        // '0' is falsy in PHP but is a perfectly valid response body. A
        // truthiness check here silently returned an empty body instead.
        response('0');

        $this->assertSame('0', (string)Response::$response->getBody());
    }

    #[Test]
    public function responseFunctionPreservesEmptyStringContent(): void
    {
        response('');

        $this->assertSame('', (string)Response::$response->getBody());
        $this->assertSame(200, Response::$response->getStatusCode());
    }

    #[Test]
    public function responseFunctionPreservesZeroStringWithStatusCode(): void
    {
        response('0', 404);

        $this->assertSame('0', (string)Response::$response->getBody());
        $this->assertSame(404, Response::$response->getStatusCode());
    }

    #[Test]
    public function contentMethodPreservesZeroString(): void
    {
        response()->content('0');

        $this->assertSame('0', (string)Response::$response->getBody());
    }

    #[Test]
    public function jsonPreservesFalsyEncodableValues(): void
    {
        // json_encode() returns the string '0' here, which is falsy but valid.
        response()->json(['count' => 0]);

        $body = (string)Response::$response->getBody();
        $this->assertJson($body);
        $this->assertSame(['count' => 0], json_decode($body, true));
        $this->assertSame('application/json', Response::$response->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function jsonStillThrowsOnUnencodableData(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to convert response to JSON');

        // Invalid UTF-8 cannot be encoded, so json_encode() returns false.
        response()->json(['bad' => "\xB1\x31"]);
    }
}
