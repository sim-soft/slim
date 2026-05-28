<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Resource;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Resource\Exceptions\InvalidResourceException;
use Simsoft\Resource\Resource;
use Simsoft\Resource\ResourceCollection;

/**
 * Property-based tests for ResourceCollection.
 *
 * @SuppressWarnings(PHPMD)
 */
class ResourceCollectionPropertyTest extends TestCase
{
    /**
     * Feature: api-resource, Property 3: Collection transformation preserves order
     *
     * For any ordered iterable of N items, the toArray() output is a sequential
     * array of exactly N transformed items in the same order.
     *
     * Validates: Requirements 3.2, 3.3, 3.6
     */
    #[Test]
    #[DataProvider('collectionOrderProvider')]
    public function collectionTransformationPreservesOrder(array $items): void
    {
        $collection = new ResourceCollection($items, CollectionTestResource::class);

        $result = $collection->toArray();

        $this->assertCount(count($items), $result);

        foreach ($items as $index => $item) {
            $expected = is_array($item) ? $item : (array)$item;
            $this->assertSame($expected, $result[$index]);
        }

        // Verify sequential (0-indexed) array keys
        if (count($items) === 0) {
            $this->assertSame([], $result);
        } else {
            $this->assertSame(array_keys($result), range(0, count($items) - 1));
        }
    }

    /**
     * Feature: api-resource, Property 4: Pagination metadata structure
     *
     * For any valid pagination parameters (total >= 0, perPage >= 1,
     * currentPage >= 1, lastPage >= 1), the collection includes them
     * under a meta key with exact integer values.
     *
     * Validates: Requirements 3.4, 3.5
     */
    #[Test]
    #[DataProvider('paginationMetadataProvider')]
    public function paginationMetadataStructure(
        int $total,
        int $perPage,
        int $currentPage,
        int $lastPage
    ): void
    {
        $items = [];
        $collection = new ResourceCollection($items, CollectionTestResource::class);
        $collection->paginate($total, $perPage, $currentPage, $lastPage);

        $result = $collection->jsonSerialize();

        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('total', $result['meta']);
        $this->assertArrayHasKey('per_page', $result['meta']);
        $this->assertArrayHasKey('current_page', $result['meta']);
        $this->assertArrayHasKey('last_page', $result['meta']);

        $this->assertSame($total, $result['meta']['total']);
        $this->assertSame($perPage, $result['meta']['per_page']);
        $this->assertSame($currentPage, $result['meta']['current_page']);
        $this->assertSame($lastPage, $result['meta']['last_page']);

        // Verify all values are integers
        $this->assertIsInt($result['meta']['total']);
        $this->assertIsInt($result['meta']['per_page']);
        $this->assertIsInt($result['meta']['current_page']);
        $this->assertIsInt($result['meta']['last_page']);
    }

    /**
     * Feature: api-resource, Property 18: Collection metadata precedence
     *
     * When both pagination and additional metadata have overlapping keys,
     * additional metadata values take precedence.
     *
     * Validates: Requirements 6.7, 6.8
     */
    #[Test]
    #[DataProvider('metadataPrecedenceProvider')]
    public function collectionMetadataPrecedence(
        int   $total,
        int   $perPage,
        int   $currentPage,
        int   $lastPage,
        array $additionalMeta
    ): void
    {
        $collection = new ResourceCollection([], CollectionTestResource::class);
        $collection->paginate($total, $perPage, $currentPage, $lastPage);
        $collection->withMeta($additionalMeta);

        $result = $collection->jsonSerialize();

        $this->assertArrayHasKey('meta', $result);

        // Additional metadata values take precedence over pagination for overlapping keys
        foreach ($additionalMeta as $key => $value) {
            $this->assertSame($value, $result['meta'][$key]);
        }

        // Non-overlapping pagination keys should still be present
        $paginationKeys = ['total', 'per_page', 'current_page', 'last_page'];
        foreach ($paginationKeys as $pKey) {
            if (!array_key_exists($pKey, $additionalMeta)) {
                $this->assertArrayHasKey($pKey, $result['meta']);
            }
        }
    }

    /**
     * Feature: api-resource, Property 19: Invalid class-string rejection
     *
     * For any string that does not reference a valid concrete Resource subclass,
     * the constructor throws InvalidResourceException.
     *
     * Validates: Requirements 3.7, 12.4
     */
    #[Test]
    #[DataProvider('invalidClassStringProvider')]
    public function invalidClassStringRejection(string $invalidClass): void
    {
        $this->expectException(InvalidResourceException::class);

        new ResourceCollection([], $invalidClass);
    }

    /**
     * @return array<int, array{0: array<int, array<string, mixed>>}>
     */
    public static function collectionOrderProvider(): array
    {
        $cases = [];

        for ($ii = 0; $ii < 100; $ii++) {
            $numItems = random_int(0, 15);
            $items = [];

            for ($jj = 0; $jj < $numItems; $jj++) {
                $items[] = self::randomAssocArray();
            }

            $cases[] = [$items];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: int, 1: int, 2: int, 3: int}>
     */
    public static function paginationMetadataProvider(): array
    {
        $cases = [];

        for ($ii = 0; $ii < 100; $ii++) {
            $total = random_int(0, 10000);
            $perPage = random_int(1, 500);
            $lastPage = random_int(1, 200);
            $currentPage = random_int(1, $lastPage);

            $cases[] = [$total, $perPage, $currentPage, $lastPage];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: int, 1: int, 2: int, 3: int, 4: array<string, mixed>}>
     */
    public static function metadataPrecedenceProvider(): array
    {
        $cases = [];
        $overlappingKeys = ['total', 'per_page', 'current_page', 'last_page'];

        for ($ii = 0; $ii < 100; $ii++) {
            $total = random_int(0, 10000);
            $perPage = random_int(1, 500);
            $lastPage = random_int(1, 200);
            $currentPage = random_int(1, $lastPage);

            $additionalMeta = [];

            // Sometimes overlap with pagination keys
            if ($ii % 3 === 0) {
                $overlapKey = $overlappingKeys[array_rand($overlappingKeys)];
                $additionalMeta[$overlapKey] = 'overridden_' . $ii;
            }

            // Always add some non-overlapping keys
            $additionalMeta['custom_key_' . $ii] = bin2hex(random_bytes(4));

            if ($ii % 2 === 0) {
                $additionalMeta['extra'] = random_int(1, 999);
            }

            $cases[] = [$total, $perPage, $currentPage, $lastPage, $additionalMeta];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function invalidClassStringProvider(): array
    {
        $cases = [];

        // Non-existent classes
        $nonExistent = [
            'NonExistentClass',
            'App\\Resource\\FakeResource',
            'Simsoft\\Resource\\NotAResource',
            'Some\\Random\\Namespace\\Thing',
        ];

        foreach ($nonExistent as $class) {
            $cases[] = [$class];
        }

        // Classes that exist but are not Resource subclasses
        $notSubclass = [
            \stdClass::class,
            \ArrayObject::class,
            \Exception::class,
            \DateTime::class,
            \SplStack::class,
            \JsonSerializable::class,
            InvalidResourceException::class,
        ];

        foreach ($notSubclass as $class) {
            $cases[] = [$class];
        }

        // The abstract Resource class itself
        $cases[] = [Resource::class];

        // Random gibberish strings
        for ($ii = 0; $ii < 85; $ii++) {
            $cases[] = [bin2hex(random_bytes(random_int(4, 20)))];
        }

        return $cases;
    }

    /**
     * @return array<string, mixed>
     */
    private static function randomAssocArray(): array
    {
        $keys = ['id', 'name', 'email', 'age', 'active', 'score', 'title', 'role', 'status', 'level'];
        $numFields = random_int(1, 6);
        $selectedKeys = array_slice($keys, 0, $numFields);
        $result = [];

        foreach ($selectedKeys as $key) {
            $result[$key] = self::randomValue();
        }

        return $result;
    }

    private static function randomValue(): mixed
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

/**
 * Concrete Resource subclass for collection property testing.
 */
class CollectionTestResource extends Resource
{
    public function toArray(): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        return (array)$this->resource;
    }
}
