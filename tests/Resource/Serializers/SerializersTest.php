<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Resource\Serializers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Resource\Exceptions\SerializationException;
use Simsoft\Resource\Resource;
use Simsoft\Resource\ResourceCollection;
use Simsoft\Resource\Serializers\JsonSerializer;
use Simsoft\Resource\Serializers\XmlSerializer;

/**
 * Unit tests for JsonSerializer and XmlSerializer.
 */
class SerializersTest extends TestCase
{
    // ─── JsonSerializer ───────────────────────────────────────────────

    #[Test]
    public function jsonSerializerSerializesResourceToValidJson(): void
    {
        $resource = new class(['id' => 1, 'name' => 'Alice']) extends Resource {
            public function toArray(): array
            {
                return ['id' => $this->resource['id'], 'name' => $this->resource['name']];
            }
        };

        $serializer = new JsonSerializer();
        $output = $serializer->serialize($resource);

        $this->assertJson($output);
        $decoded = json_decode($output, true);
        $this->assertSame(['data' => ['id' => 1, 'name' => 'Alice']], $decoded);
    }

    #[Test]
    public function jsonSerializerSerializesResourceCollectionToValidJson(): void
    {
        $items = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $collection = new ResourceCollection($items, SimpleTestResource::class);

        $serializer = new JsonSerializer();
        $output = $serializer->serialize($collection);

        $this->assertJson($output);
        $decoded = json_decode($output, true);
        $this->assertSame([
            'data' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
        ], $decoded);
    }

    #[Test]
    public function jsonSerializerUsesPrettyPrintAndUnescapedSlashes(): void
    {
        $resource = new class(['url' => 'https://example.com/users/1']) extends Resource {
            public function toArray(): array
            {
                return ['url' => $this->resource['url']];
            }
        };

        $serializer = new JsonSerializer();
        $output = $serializer->serialize($resource);

        // Pretty print produces newlines and indentation
        $this->assertStringContainsString("\n", $output);
        // Unescaped slashes: no \/ in output
        $this->assertStringNotContainsString('\\/', $output);
        $this->assertStringContainsString('https://example.com/users/1', $output);
    }

    #[Test]
    public function jsonSerializerContentTypeReturnsApplicationJson(): void
    {
        $serializer = new JsonSerializer();

        $this->assertSame('application/json', $serializer->contentType());
    }

    #[Test]
    public function jsonSerializerThrowsSerializationExceptionOnEncodingFailure(): void
    {
        $resource = new class(['bad' => "\xB1\x31"]) extends Resource {
            public function toArray(): array
            {
                return ['value' => $this->resource['bad']];
            }
        };

        $serializer = new JsonSerializer();

        $this->expectException(SerializationException::class);
        $serializer->serialize($resource);
    }

    // ─── XmlSerializer ────────────────────────────────────────────────

    #[Test]
    public function xmlSerializerSerializesResourceToWellFormedXml(): void
    {
        $resource = new class(['id' => 1, 'name' => 'Alice']) extends Resource {
            public function toArray(): array
            {
                return ['id' => $this->resource['id'], 'name' => $this->resource['name']];
            }
        };

        $serializer = new XmlSerializer();
        $output = $serializer->serialize($resource);

        // Should be parseable XML
        $xml = simplexml_load_string($output);
        $this->assertNotFalse($xml);
    }

    #[Test]
    public function xmlSerializerOutputStartsWithXmlDeclaration(): void
    {
        $resource = new class(['id' => 1]) extends Resource {
            public function toArray(): array
            {
                return ['id' => $this->resource['id']];
            }
        };

        $serializer = new XmlSerializer();
        $output = $serializer->serialize($resource);

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $output);
    }

    #[Test]
    public function xmlSerializerRootElementIsResponse(): void
    {
        $resource = new class(['id' => 1]) extends Resource {
            public function toArray(): array
            {
                return ['id' => $this->resource['id']];
            }
        };

        $serializer = new XmlSerializer();
        $output = $serializer->serialize($resource);

        $xml = simplexml_load_string($output);
        $this->assertNotFalse($xml);
        $this->assertSame('response', $xml->getName());
    }

    #[Test]
    public function xmlSerializerTopLevelKeysAreChildElements(): void
    {
        $resource = new class(['id' => 42, 'name' => 'Test']) extends Resource {
            public function toArray(): array
            {
                return ['id' => $this->resource['id'], 'name' => $this->resource['name']];
            }
        };

        $serializer = new XmlSerializer();
        $output = $serializer->serialize($resource);

        $xml = simplexml_load_string($output);
        $this->assertNotFalse($xml);
        // The envelope wraps data under <data>
        $this->assertNotNull($xml->data);
        $this->assertSame('42', (string)$xml->data->id);
        $this->assertSame('Test', (string)$xml->data->name);
    }

    #[Test]
    public function xmlSerializerContentTypeReturnsApplicationXml(): void
    {
        $serializer = new XmlSerializer();

        $this->assertSame('application/xml', $serializer->contentType());
    }

    #[Test]
    public function xmlSerializerHandlesNestedArrays(): void
    {
        $resource = new class(['address' => ['city' => 'NYC', 'zip' => '10001']]) extends Resource {
            public function toArray(): array
            {
                return ['address' => $this->resource['address']];
            }
        };

        $serializer = new XmlSerializer();
        $output = $serializer->serialize($resource);

        $xml = simplexml_load_string($output);
        $this->assertNotFalse($xml);
        $this->assertSame('NYC', (string)$xml->data->address->city);
        $this->assertSame('10001', (string)$xml->data->address->zip);
    }

    #[Test]
    public function xmlSerializerHandlesNullValues(): void
    {
        $resource = new class(['name' => null]) extends Resource {
            public function toArray(): array
            {
                return ['name' => null];
            }
        };

        $serializer = new XmlSerializer();
        $output = $serializer->serialize($resource);

        $xml = simplexml_load_string($output);
        $this->assertNotFalse($xml);
        // Null produces an empty element
        $this->assertSame('', (string)$xml->data->name);
    }

    #[Test]
    public function xmlSerializerHandlesBooleanValues(): void
    {
        $resource = new class(['active' => true, 'deleted' => false]) extends Resource {
            public function toArray(): array
            {
                return ['active' => true, 'deleted' => false];
            }
        };

        $serializer = new XmlSerializer();
        $output = $serializer->serialize($resource);

        $xml = simplexml_load_string($output);
        $this->assertNotFalse($xml);
        $this->assertSame('true', (string)$xml->data->active);
        $this->assertSame('false', (string)$xml->data->deleted);
    }

    #[Test]
    public function xmlSerializerHandlesSequentialArraysWithItemElements(): void
    {
        $resource = new class(['tags' => ['php', 'api', 'rest']]) extends Resource {
            public function toArray(): array
            {
                return ['tags' => $this->resource['tags']];
            }
        };

        $serializer = new XmlSerializer();
        $output = $serializer->serialize($resource);

        $xml = simplexml_load_string($output);
        $this->assertNotFalse($xml);
        // Sequential arrays use <item> elements
        $items = $xml->data->tags->item;
        $this->assertCount(3, $items);
        $this->assertSame('php', (string)$items[0]);
        $this->assertSame('api', (string)$items[1]);
        $this->assertSame('rest', (string)$items[2]);
    }
}

/**
 * Simple concrete Resource subclass for testing serializers.
 */
class SimpleTestResource extends Resource
{
    public function toArray(): array
    {
        $data = (array)$this->resource;

        return ['id' => $data['id'], 'name' => $data['name']];
    }
}
