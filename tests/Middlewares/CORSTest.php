<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\CORS;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class CORSTest extends TestCase
{
    private ServerRequestInterface $request;
    private RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        $this->request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = (new ResponseFactory())->createResponse();
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->handler->method('handle')->willReturn($response);
    }

    #[Test]
    public function defaultOriginIsWildcard(): void
    {
        $cors = new CORS();
        $response = $cors($this->request, $this->handler);

        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function defaultMethodsAreSet(): void
    {
        $cors = new CORS();
        $response = $cors($this->request, $this->handler);

        $methods = $response->getHeaderLine('Access-Control-Allow-Methods');
        $this->assertStringContainsString('GET', $methods);
        $this->assertStringContainsString('POST', $methods);
        $this->assertStringContainsString('PUT', $methods);
        $this->assertStringContainsString('DELETE', $methods);
        $this->assertStringContainsString('PATCH', $methods);
        $this->assertStringContainsString('OPTIONS', $methods);
    }

    #[Test]
    public function defaultHeadersAreSet(): void
    {
        $cors = new CORS();
        $response = $cors($this->request, $this->handler);

        $headers = $response->getHeaderLine('Access-Control-Allow-Headers');
        $this->assertStringContainsString('X-Requested-With', $headers);
        $this->assertStringContainsString('Content-Type', $headers);
        $this->assertStringContainsString('Accept', $headers);
        $this->assertStringContainsString('Origin', $headers);
        $this->assertStringContainsString('Authorization', $headers);
    }

    #[Test]
    public function credentialsDefaultToFalse(): void
    {
        $cors = new CORS();
        $response = $cors($this->request, $this->handler);

        $this->assertSame('false', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    #[Test]
    public function customOrigin(): void
    {
        $cors = new CORS('https://myapp.com');
        $response = $cors($this->request, $this->handler);

        $this->assertSame('https://myapp.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function customMethods(): void
    {
        $cors = new CORS('*', 'GET,POST');
        $response = $cors($this->request, $this->handler);

        $this->assertSame('GET,POST', $response->getHeaderLine('Access-Control-Allow-Methods'));
    }

    #[Test]
    public function methodsAreUppercased(): void
    {
        $cors = new CORS('*', 'get,post');
        $response = $cors($this->request, $this->handler);

        $this->assertSame('GET,POST', $response->getHeaderLine('Access-Control-Allow-Methods'));
    }

    #[Test]
    public function allowAddsCustomHeader(): void
    {
        $cors = new CORS();
        $cors->allow('Credentials', 'true');
        $response = $cors($this->request, $this->handler);

        $this->assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    #[Test]
    public function allowReturnsSelfForChaining(): void
    {
        $cors = new CORS();
        $result = $cors->allow('Credentials', 'true');

        $this->assertSame($cors, $result);
    }

    #[Test]
    public function allowMultipleCustomHeaders(): void
    {
        $cors = new CORS();
        $cors->allow('Credentials', 'true')
            ->allow('Max-Age', '3600');

        $response = $cors($this->request, $this->handler);

        $this->assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertSame('3600', $response->getHeaderLine('Access-Control-Allow-Max-Age'));
    }

    #[Test]
    public function returnsResponseInterface(): void
    {
        $cors = new CORS();
        $response = $cors($this->request, $this->handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function preservesExistingResponseBody(): void
    {
        $responseWithBody = (new ResponseFactory())->createResponse();
        $responseWithBody->getBody()->write('api response');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($responseWithBody);

        $cors = new CORS();
        $response = $cors($this->request, $handler);

        $this->assertSame('api response', (string)$response->getBody());
    }

    #[Test]
    public function multipleOriginsWithNoMatchReturnsNull(): void
    {
        // Without HTTP_ORIGIN set, parseOrigins returns 'null'
        $cors = new CORS('https://app1.com,https://app2.com');
        $response = $cors($this->request, $this->handler);

        $this->assertSame('null', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function parseOriginsWithMatchingOrigin(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://app1.com';

        $cors = new CORS('https://app1.com,https://app2.com');
        $response = $cors($this->request, $this->handler);

        $this->assertSame('https://app1.com', $response->getHeaderLine('Access-Control-Allow-Origin'));

        unset($_SERVER['HTTP_ORIGIN']);
    }

    #[Test]
    public function parseOriginsWithNonMatchingOrigin(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.com';

        $cors = new CORS('https://app1.com,https://app2.com');
        $response = $cors($this->request, $this->handler);

        $this->assertSame('null', $response->getHeaderLine('Access-Control-Allow-Origin'));

        unset($_SERVER['HTTP_ORIGIN']);
    }

    #[Test]
    public function parseOriginsWithRefererFallback(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://app2.com';

        $cors = new CORS('https://app1.com,https://app2.com');
        $response = $cors($this->request, $this->handler);

        $this->assertSame('https://app2.com', $response->getHeaderLine('Access-Control-Allow-Origin'));

        unset($_SERVER['HTTP_REFERER']);
    }

    #[Test]
    public function parseOriginsHandlesSpacesInList(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://app1.com';

        $cors = new CORS('https://app1.com, https://app2.com');
        $response = $cors($this->request, $this->handler);

        $this->assertSame('https://app1.com', $response->getHeaderLine('Access-Control-Allow-Origin'));

        unset($_SERVER['HTTP_ORIGIN']);
    }
}
