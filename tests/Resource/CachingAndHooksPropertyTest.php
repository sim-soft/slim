<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Resource;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Resource\Resource;

/**
 * Property-based tests for caching, hooks, and type identifier.
 *
 * @SuppressWarnings(PHPMD)
 */
class CachingAndHooksPropertyTest extends TestCase
{
    /**
     * Feature: api-resource, Property 11: Serialization idempotence (caching)
     *
     * Calling jsonSerialize() multiple times without intervening withContext()
     * returns identical results. Calling withContext() invalidates the cache.
     *
     * Validates: Requirements 14.1, 14.2, 14.3, 14.5
     */
    #[Test]
    #[DataProvider('cachingIdempotenceDataProvider')]
    public function serializationIdempotence(array $data): void
    {
        $resource = new class ($data) extends Resource {
            protected bool $wrap = false;
            private int $callCount = 0;

            public function toArray(): array
            {
                $this->callCount++;
                return $this->resource;
            }

            public function getCallCount(): int
            {
                return $this->callCount;
            }
        };

        // First call
        $first = $resource->jsonSerialize();

        // Second call — should return identical result
        $second = $resource->jsonSerialize();

        // Property: multiple calls return identical results
        $this->assertSame($first, $second);

        // Property: toArray() invoked exactly once (cached)
        $this->assertSame(1, $resource->getCallCount());

        // Now invalidate cache with withContext
        $resource->withContext(['invalidate' => true]);

        // Third call — should recompute
        $third = $resource->jsonSerialize();

        // Property: after withContext(), toArray() is re-invoked
        $this->assertSame(2, $resource->getCallCount());

        // Property: result is still structurally identical (same data)
        $this->assertSame($first, $third);
    }

    /**
     * Feature: api-resource, Property 15: afterSerialize pipeline
     *
     * The afterSerialize() method receives the filtered output and its return
     * value replaces the output entirely.
     *
     * Validates: Requirements 17.1, 17.2, 17.3
     */
    #[Test]
    #[DataProvider('afterSerializePipelineDataProvider')]
    public function afterSerializePipeline(array $data, string $addedKey, mixed $addedValue): void
    {
        $resource = new class ($data, $addedKey, $addedValue) extends Resource {
            protected bool $wrap = false;

            public function __construct(
                object|array   $resource,
                private string $addedKey,
                private mixed  $addedValue,
            )
            {
                parent::__construct($resource);
            }

            public function toArray(): array
            {
                return $this->resource;
            }

            protected function afterSerialize(array $data): array
            {
                $data[$this->addedKey] = $this->addedValue;
                return $data;
            }
        };

        $result = $resource->jsonSerialize();

        // Property: afterSerialize return value replaces the output
        $this->assertArrayHasKey($addedKey, $result);
        $this->assertSame($addedValue, $result[$addedKey]);

        // Property: original data fields are still present (afterSerialize adds, doesn't remove)
        foreach ($data as $key => $value) {
            $this->assertArrayHasKey($key, $result);
            $this->assertSame($value, $result[$key]);
        }
    }

    /**
     * Feature: api-resource, Property 15 (continued): default afterSerialize is identity
     *
     * Validates: Requirements 17.3
     */
    #[Test]
    #[DataProvider('identityAfterSerializeDataProvider')]
    public function defaultAfterSerializeIsIdentity(array $data): void
    {
        $resource = new class ($data) extends Resource {
            protected bool $wrap = false;

            public function toArray(): array
            {
                return $this->resource;
            }
        };

        $result = $resource->jsonSerialize();

        // Property: default afterSerialize returns input unchanged
        $this->assertSame($data, $result);
    }

    /**
     * Feature: api-resource, Property 16: Type identifier in envelope
     *
     * For any Resource with a non-null, non-empty $type and wrapping enabled,
     * the envelope includes a type field. Empty/whitespace types are omitted.
     *
     * Validates: Requirements 21.2, 21.6, 21.7
     */
    #[Test]
    #[DataProvider('typeIdentifierDataProvider')]
    public function typeIdentifierInEnvelope(array $data, ?string $typeValue, bool $shouldIncludeType): void
    {
        $resource = new class ($data, $typeValue) extends Resource {
            public function __construct(
                object|array $resource,
                ?string      $typeValue,
            )
            {
                parent::__construct($resource);
                $this->type = $typeValue;
            }

            public function toArray(): array
            {
                return $this->resource;
            }
        };

        $result = $resource->jsonSerialize();

        if ($shouldIncludeType) {
            // Property: envelope includes type field with trimmed value
            $this->assertArrayHasKey('type', $result);
            $this->assertSame(trim($typeValue), $result['type']);
        } else {
            // Property: empty/whitespace/null types are omitted
            $this->assertArrayNotHasKey('type', $result);
        }

        // Property: data is always present under wrapKey when wrapping enabled
        $this->assertArrayHasKey('data', $result);
        $this->assertSame($data, $result['data']);
    }

    /**
     * @return array<int, array{0: array<string, mixed>}>
     */
    public static function cachingIdempotenceDataProvider(): array
    {
        $cases = [];

        for ($ii = 0; $ii < 100; $ii++) {
            $cases[] = [self::randomAssocArray()];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: string, 2: mixed}>
     */
    public static function afterSerializePipelineDataProvider(): array
    {
        $cases = [];
        $addedKeys = ['computed', 'derived', 'extra', 'appended', 'generated'];

        for ($ii = 0; $ii < 100; $ii++) {
            $data = self::randomAssocArray();
            $addedKey = $addedKeys[array_rand($addedKeys)] . '_' . $ii;
            $addedValue = self::randomScalar();
            $cases[] = [$data, $addedKey, $addedValue];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: array<string, mixed>}>
     */
    public static function identityAfterSerializeDataProvider(): array
    {
        $cases = [];

        for ($ii = 0; $ii < 100; $ii++) {
            $cases[] = [self::randomAssocArray()];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: ?string, 2: bool}>
     */
    public static function typeIdentifierDataProvider(): array
    {
        $cases = [];
        $validTypes = ['user', 'post', 'comment', 'article', 'product', 'order'];

        for ($ii = 0; $ii < 100; $ii++) {
            $data = self::randomAssocArray();
            $variant = $ii % 5;

            switch ($variant) {
                case 0:
                    // Non-null, non-empty type — should be included
                    $typeValue = $validTypes[array_rand($validTypes)];
                    $shouldInclude = true;
                    break;
                case 1:
                    // Null type — should be omitted
                    $typeValue = null;
                    $shouldInclude = false;
                    break;
                case 2:
                    // Empty string — should be omitted
                    $typeValue = '';
                    $shouldInclude = false;
                    break;
                case 3:
                    // Whitespace only — should be omitted
                    $typeValue = '   ';
                    $shouldInclude = false;
                    break;
                default:
                    // Type with leading/trailing whitespace — should be trimmed and included
                    $typeValue = '  ' . $validTypes[array_rand($validTypes)] . '  ';
                    $shouldInclude = true;
                    break;
            }

            $cases[] = [$data, $typeValue, $shouldInclude];
        }

        return $cases;
    }

    /**
     * @return array<string, mixed>
     */
    private static function randomAssocArray(): array
    {
        $keys = ['id', 'name', 'email', 'age', 'active', 'score', 'title', 'role'];
        $numFields = random_int(2, 5);
        $selectedKeys = array_slice($keys, 0, $numFields);
        $result = [];

        foreach ($selectedKeys as $key) {
            $result[$key] = self::randomScalar();
        }

        return $result;
    }

    private static function randomScalar(): int|string|bool|float
    {
        $type = random_int(0, 3);

        return match ($type) {
            0 => random_int(1, 9999),
            1 => bin2hex(random_bytes(4)),
            2 => random_int(0, 1) === 1,
            default => random_int(1, 100) / 10.0,
        };
    }
}
