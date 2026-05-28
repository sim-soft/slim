<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Resource;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Resource\Exceptions\InvalidStatusCodeException;
use Simsoft\Resource\Resource;
use Simsoft\Resource\ResourceCollection;
use Simsoft\Resource\Serializers\ResourceSerializerInterface;
use Simsoft\Resource\Serializers\XmlSerializer;
use Simsoft\Slim\Response;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Unit tests for Response::resource() adapter method.
 */
class ResponseResourceTest extends TestCase
{
    protected function setUp(): void
    {
        Response::$response = (new ResponseFactory())->createResponse();
    }

    #[Test]
    public function resourceDefaultsToJsonSerializationWithStatus200(): void
    {
        $resource = new class(['id' => 1, 'name' => 'Alice']) extends Resource {
            public function toArray(): array
            {
                return ['id' => $this->resource['id'], 'name' => $this->resource['name']];
            }
        };

        $response = Response::getInstance();
        $response->resource($resource);

        $body = (string)Response::$response->getBody();
        $this->assertJson($body);
        $decoded = json_decode($body, true);
        $this->assertSame(['data' => ['id' => 1, 'name' => 'Alice']], $decoded);
        $this->assertSame(200, Response::$response->getStatusCode());
        $this->assertSame('application/json', Response::$response->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function resourceWithCustomStatusCode201(): void
    {
        $resource = new class(['id' => 5]) extends Resource {
            public function toArray(): array
            {
                return ['id' => $this->resource['id']];
            }
        };

        $response = Response::getInstance();
        $response->resource($resource, 201);

        $this->assertSame(201, Response::$response->getStatusCode());
    }

    #[Test]
    public function resourceWithCustomStatusCode404(): void
    {
        $resource = new class(['error' => 'not found']) extends Resource {
            public function toArray(): array
            {
                return ['error' => $this->resource['error']];
            }
        };

        $response = Response::getInstance();
        $response->resource($resource, 404);

        $this->assertSame(404, Response::$response->getStatusCode());
    }

    #[Test]
    public function resourceThrowsInvalidStatusCodeExceptionForCodeZero(): void
    {
        $resource = new class(['id' => 1]) extends Resource {
            public function toArray(): array
            {
                return ['id' => 1];
            }
        };

        $response = Response::getInstance();

        $this->expectException(InvalidStatusCodeException::class);
        $response->resource($resource, 0);
    }

    #[Test]
    public function resourceThrowsInvalidStatusCodeExceptionForCode600(): void
    {
        $resource = new class(['id' => 1]) extends Resource {
            public function toArray(): array
            {
                return ['id' => 1];
            }
        };

        $response = Response::getInstance();

        $this->expectException(InvalidStatusCodeException::class);
        $response->resource($resource, 600);
    }

    #[Test]
    public function resourceThrowsInvalidStatusCodeExceptionForNegativeCode(): void
    {
        $resource = new class(['id' => 1]) extends Resource {
            public function toArray(): array
            {
                return ['id' => 1];
            }
        };

        $response = Response::getInstance();

        $this->expectException(InvalidStatusCodeException::class);
        $response->resource($resource, -1);
    }

    #[Test]
    public function resourceAppliesResourceHeadersToResponse(): void
    {
        $resource = new class(['id' => 1]) extends Resource {
            public function toArray(): array
            {
                return ['id' => 1];
            }
        };
        $resource->withHeaders(['X-Custom-Header' => 'custom-value', 'X-Request-Id' => 'abc123']);

        $response = Response::getInstance();
        $response->resource($resource);

        $this->assertSame('custom-value', Response::$response->getHeaderLine('X-Custom-Header'));
        $this->assertSame('abc123', Response::$response->getHeaderLine('X-Request-Id'));
    }

    #[Test]
    public function resourceWithCustomSerializerChangesContentType(): void
    {
        $resource = new class(['id' => 1, 'name' => 'Test']) extends Resource {
            public function toArray(): array
            {
                return ['id' => $this->resource['id'], 'name' => $this->resource['name']];
            }
        };

        $xmlSerializer = new XmlSerializer();

        $response = Response::getInstance();
        $response->resource($resource, 200, $xmlSerializer);

        $this->assertSame('application/xml', Response::$response->getHeaderLine('Content-Type'));
        $body = (string)Response::$response->getBody();
        $this->assertStringStartsWith('<?xml', $body);
    }

    #[Test]
    public function resourceWithNoSerializerDefaultsToJson(): void
    {
        $resource = new class(['key' => 'value']) extends Resource {
            public function toArray(): array
            {
                return ['key' => $this->resource['key']];
            }
        };

        $response = Response::getInstance();
        $response->resource($resource);

        $this->assertSame('application/json', Response::$response->getHeaderLine('Content-Type'));
        $body = (string)Response::$response->getBody();
        $this->assertJson($body);
    }
}
