<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Slim\Response;
use Slim\Psr7\Factory\ResponseFactory;

class ResponseTest extends TestCase
{
    protected function setUp(): void
    {
        Response::$response = (new ResponseFactory())->createResponse();
    }

    #[Test]
    public function getInstanceReturnsResponseObject(): void
    {
        $instance = Response::getInstance();
        $this->assertInstanceOf(Response::class, $instance);
    }

    #[Test]
    public function contentWritesStringToBody(): void
    {
        $response = Response::getInstance();
        $response->content('Hello World');

        $this->assertSame('Hello World', (string)Response::$response->getBody());
    }

    #[Test]
    public function contentWithArrayDelegatesToJson(): void
    {
        $response = Response::getInstance();
        $response->content(['key' => 'value']);

        $body = (string)Response::$response->getBody();
        $this->assertJson($body);
        $this->assertSame(['key' => 'value'], json_decode($body, true));
        $this->assertSame('application/json', Response::$response->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function statusSetsStatusCode(): void
    {
        $response = Response::getInstance();
        $response->status(404);

        $this->assertSame(404, Response::$response->getStatusCode());
    }

    #[Test]
    public function statusSetsReasonPhrase(): void
    {
        $response = Response::getInstance();
        $response->status(422, 'Unprocessable Entity');

        $this->assertSame(422, Response::$response->getStatusCode());
        $this->assertSame('Unprocessable Entity', Response::$response->getReasonPhrase());
    }

    #[Test]
    public function jsonWritesJsonContentAndSetsHeader(): void
    {
        $response = Response::getInstance();
        $data = ['status' => 'success', 'items' => [1, 2, 3]];
        $response->json($data);

        $body = (string)Response::$response->getBody();
        $this->assertJson($body);
        $this->assertSame($data, json_decode($body, true));
        $this->assertSame('application/json', Response::$response->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function jsonWithPrettyPrintAndUnescapedSlashes(): void
    {
        $response = Response::getInstance();
        $data = ['url' => 'https://example.com/path'];
        $response->json($data);

        $body = (string)Response::$response->getBody();
        $this->assertStringContainsString('https://example.com/path', $body);
        $this->assertStringNotContainsString('\\/', $body);
    }

    #[Test]
    public function xmlWritesContentAndSetsHeader(): void
    {
        $response = Response::getInstance();
        $xml = '<root><item>test</item></root>';
        $response->xml($xml);

        $this->assertSame($xml, (string)Response::$response->getBody());
        $this->assertSame('text/xml', Response::$response->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function headerSetsResponseHeader(): void
    {
        $response = Response::getInstance();
        $response->header('X-Custom', 'test-value');

        $this->assertSame('test-value', Response::$response->getHeaderLine('X-Custom'));
    }

    #[Test]
    public function withHeadersSetsMultipleHeaders(): void
    {
        $response = Response::getInstance();
        $response->withHeaders([
            'X-First' => 'one',
            'X-Second' => 'two',
        ]);

        $this->assertSame('one', Response::$response->getHeaderLine('X-First'));
        $this->assertSame('two', Response::$response->getHeaderLine('X-Second'));
    }

    #[Test]
    public function redirectSetsLocationAndStatus(): void
    {
        $response = Response::getInstance();
        $response->redirect('https://example.com');

        $this->assertSame('https://example.com', Response::$response->getHeaderLine('Location'));
        $this->assertSame(302, Response::$response->getStatusCode());
    }

    #[Test]
    public function redirectWithCustomStatusCode(): void
    {
        $response = Response::getInstance();
        $response->redirect('https://example.com', 301);

        $this->assertSame('https://example.com', Response::$response->getHeaderLine('Location'));
        $this->assertSame(301, Response::$response->getStatusCode());
    }

    #[Test]
    public function fluentInterfaceChaining(): void
    {
        $response = Response::getInstance();
        $result = $response->content('test')->status(201)->header('X-App', 'slim');

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(201, Response::$response->getStatusCode());
        $this->assertSame('slim', Response::$response->getHeaderLine('X-App'));
    }

    #[Test]
    public function magicCallDelegatesToPsr7Response(): void
    {
        $response = Response::getInstance();
        $statusCode = $response->getStatusCode();

        $this->assertSame(200, $statusCode);
    }

    #[Test]
    public function magicCallWithNonExistentMethodReturnsNull(): void
    {
        $response = Response::getInstance();
        $result = $response->nonExistentMethod();

        $this->assertNull($result);
    }

    #[Test]
    public function magicCallUpdatesStaticResponseForMutatingMethods(): void
    {
        $response = Response::getInstance();
        $response->withStatus(418);

        $this->assertSame(418, Response::$response->getStatusCode());
    }

    #[Test]
    public function contentAppendsToExistingBody(): void
    {
        $response = Response::getInstance();
        $response->content('Hello ');
        $response->content('World');

        $this->assertSame('Hello World', (string)Response::$response->getBody());
    }

    #[Test]
    public function emptyJsonArray(): void
    {
        $response = Response::getInstance();
        $response->json([]);

        $body = (string)Response::$response->getBody();
        $this->assertSame('[]', $body);
    }

    #[Test]
    public function jsonWithNestedStructure(): void
    {
        $response = Response::getInstance();
        $data = [
            'user' => [
                'name' => 'John',
                'roles' => ['admin', 'editor'],
            ],
        ];
        $response->json($data);

        $body = (string)Response::$response->getBody();
        $this->assertSame($data, json_decode($body, true));
    }
}
