<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Resource;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Resource\Resource;

/**
 * Property-based tests for field filtering and declarative mapping.
 *
 * @SuppressWarnings(PHPMD)
 */
class FieldFilteringPropertyTest extends TestCase
{
    /**
     * Feature: api-resource, Property 12: Field filtering with only
     *
     * For any Resource and field list, the serialized output contains only keys
     * present in both the toArray() output and the field list.
     *
     * Validates: Requirements 16.2, 16.4
     */
    #[Test]
    #[DataProvider('onlyFilterDataProvider')]
    public function fieldFilteringWithOnly(array $data, array $onlyFields): void
    {
        $resource = new class ($data) extends Resource {
            protected bool $wrap = false;

            public function toArray(): array
            {
                return $this->resource;
            }
        };

        $resource->only(...$onlyFields);
        $result = $resource->jsonSerialize();

        // Property: output contains only keys present in BOTH toArray() and the field list
        $expectedKeys = array_values(array_intersect(array_keys($data), $onlyFields));
        $resultKeys = array_values(array_keys($result));

        sort($expectedKeys);
        sort($resultKeys);

        $this->assertSame($expectedKeys, $resultKeys);

        foreach ($result as $key => $value) {
            $this->assertContains($key, $onlyFields);
            $this->assertArrayHasKey($key, $data);
            $this->assertSame($data[$key], $value);
        }
    }

    /**
     * Feature: api-resource, Property 13: Field exclusion with except
     *
     * For any Resource and field list, the serialized output contains all keys
     * from toArray() except those in the exclusion list. If both only and except
     * are set, only takes precedence.
     *
     * Validates: Requirements 20.2, 20.4, 20.5
     */
    #[Test]
    #[DataProvider('exceptFilterDataProvider')]
    public function fieldExclusionWithExcept(array $data, array $exceptFields): void
    {
        $resource = new class ($data) extends Resource {
            protected bool $wrap = false;

            public function toArray(): array
            {
                return $this->resource;
            }
        };

        $resource->except(...$exceptFields);
        $result = $resource->jsonSerialize();

        // Property: output contains all keys from toArray() except those in the exclusion list
        $expectedKeys = array_diff(array_keys($data), $exceptFields);

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result);
            $this->assertSame($data[$key], $result[$key]);
        }

        foreach ($exceptFields as $field) {
            $this->assertArrayNotHasKey($field, $result);
        }
    }

    /**
     * Feature: api-resource, Property 13 (continued): only takes precedence over except
     *
     * Validates: Requirements 20.5
     */
    #[Test]
    #[DataProvider('onlyPrecedenceDataProvider')]
    public function onlyTakesPrecedenceOverExcept(array $data, array $onlyFields, array $exceptFields): void
    {
        $resource = new class ($data) extends Resource {
            protected bool $wrap = false;

            public function toArray(): array
            {
                return $this->resource;
            }
        };

        // Set except first, then only — only should take precedence
        $resource->except(...$exceptFields);
        $resource->only(...$onlyFields);
        $result = $resource->jsonSerialize();

        // Property: only takes precedence, except is ignored
        $expectedKeys = array_values(array_intersect(array_keys($data), $onlyFields));
        $resultKeys = array_values(array_keys($result));

        sort($expectedKeys);
        sort($resultKeys);

        // Property: result keys are exactly the intersection of data keys and only fields
        $this->assertSame($expectedKeys, $resultKeys);

        // No keys outside the only list should appear
        foreach (array_keys($result) as $key) {
            $this->assertContains($key, $onlyFields);
        }
    }

    /**
     * Feature: api-resource, Property 14: Declarative mapping resolution
     *
     * For any Resource with a non-empty $map property, the output contains one entry
     * per map key where the value is resolved from the data item using dot-notation.
     *
     * Validates: Requirements 19.2, 19.3, 19.4, 19.5, 19.6
     */
    #[Test]
    #[DataProvider('mappingResolutionDataProvider')]
    public function declarativeMappingResolution(array $data, array $mapConfig, array $expectedOutput): void
    {
        $resource = new class ($data, $mapConfig) extends Resource {
            /** @var array<string, string> */
            protected array $map = [];

            public function __construct(
                object|array $resource,
                array        $mapConfig,
            )
            {
                parent::__construct($resource);
                $this->map = $mapConfig;
            }
        };

        $resource = $resource->only(...array_keys($mapConfig));

        // Use unwrapped for simpler assertion
        $unwrapped = new class ($data, $mapConfig) extends Resource {
            protected bool $wrap = false;

            /** @var array<string, string> */
            protected array $map = [];

            public function __construct(
                object|array $resource,
                array        $mapConfig,
            )
            {
                parent::__construct($resource);
                $this->map = $mapConfig;
            }
        };

        $result = $unwrapped->jsonSerialize();

        // Property: output contains one entry per map key
        $this->assertSame(array_keys($mapConfig), array_keys($result));

        // Property: values resolved via dot-notation
        foreach ($expectedOutput as $key => $expectedValue) {
            $this->assertArrayHasKey($key, $result);
            $this->assertSame($expectedValue, $result[$key]);
        }
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: string[]}>
     */
    public static function onlyFilterDataProvider(): array
    {
        $cases = [];
        $allKeys = ['id', 'name', 'email', 'age', 'active', 'score', 'title', 'role'];

        for ($ii = 0; $ii < 100; $ii++) {
            $numDataKeys = random_int(3, 7);
            $dataKeys = array_slice($allKeys, 0, $numDataKeys);
            $data = [];
            foreach ($dataKeys as $key) {
                $data[$key] = self::randomScalar();
            }

            // Select a subset (possibly including keys not in data)
            $numOnlyKeys = random_int(1, 5);
            $onlyFields = [];
            for ($jj = 0; $jj < $numOnlyKeys; $jj++) {
                $onlyFields[] = $allKeys[array_rand($allKeys)];
            }
            $onlyFields = array_unique($onlyFields);

            $cases[] = [$data, array_values($onlyFields)];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: string[]}>
     */
    public static function exceptFilterDataProvider(): array
    {
        $cases = [];
        $allKeys = ['id', 'name', 'email', 'age', 'active', 'score', 'title', 'role'];

        for ($ii = 0; $ii < 100; $ii++) {
            $numDataKeys = random_int(3, 7);
            $dataKeys = array_slice($allKeys, 0, $numDataKeys);
            $data = [];
            foreach ($dataKeys as $key) {
                $data[$key] = self::randomScalar();
            }

            // Select fields to exclude
            $numExceptKeys = random_int(1, 3);
            $exceptFields = [];
            for ($jj = 0; $jj < $numExceptKeys; $jj++) {
                $exceptFields[] = $allKeys[array_rand($allKeys)];
            }
            $exceptFields = array_unique($exceptFields);

            $cases[] = [$data, array_values($exceptFields)];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: string[], 2: string[]}>
     */
    public static function onlyPrecedenceDataProvider(): array
    {
        $cases = [];
        $allKeys = ['id', 'name', 'email', 'age', 'active', 'score', 'title', 'role'];

        for ($ii = 0; $ii < 100; $ii++) {
            $numDataKeys = random_int(4, 7);
            $dataKeys = array_slice($allKeys, 0, $numDataKeys);
            $data = [];
            foreach ($dataKeys as $key) {
                $data[$key] = self::randomScalar();
            }

            // Ensure at least one only field overlaps with data keys
            $guaranteedKey = $dataKeys[array_rand($dataKeys)];
            $numOnlyKeys = random_int(1, 3);
            $onlyFields = [$guaranteedKey];
            for ($jj = 1; $jj < $numOnlyKeys; $jj++) {
                $onlyFields[] = $allKeys[array_rand($allKeys)];
            }
            $onlyFields = array_values(array_unique($onlyFields));

            $numExceptKeys = random_int(1, 3);
            $exceptFields = [];
            for ($jj = 0; $jj < $numExceptKeys; $jj++) {
                $exceptFields[] = $allKeys[array_rand($allKeys)];
            }
            $exceptFields = array_values(array_unique($exceptFields));

            $cases[] = [$data, $onlyFields, $exceptFields];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: array<string, string>, 2: array<string, mixed>}>
     */
    public static function mappingResolutionDataProvider(): array
    {
        $cases = [];

        for ($ii = 0; $ii < 100; $ii++) {
            $data = self::randomNestedData();
            $mapConfig = [];
            $expectedOutput = [];

            // Direct field access
            $directKey = 'direct_' . $ii;
            $mapConfig[$directKey] = 'name';
            $expectedOutput[$directKey] = $data['name'] ?? null;

            // Nested dot-notation access
            $nestedKey = 'nested_' . $ii;
            $mapConfig[$nestedKey] = 'address.city';
            $expectedOutput[$nestedKey] = $data['address']['city'] ?? null;

            // Missing path resolves to null
            $missingKey = 'missing_' . $ii;
            $mapConfig[$missingKey] = 'nonexistent.path';
            $expectedOutput[$missingKey] = null;

            $cases[] = [$data, $mapConfig, $expectedOutput];
        }

        return $cases;
    }

    /**
     * @return array<string, mixed>
     */
    private static function randomNestedData(): array
    {
        $hasAddress = random_int(0, 1) === 1;
        $data = [
            'name' => bin2hex(random_bytes(4)),
            'age' => random_int(18, 80),
        ];

        if ($hasAddress) {
            $data['address'] = [
                'city' => bin2hex(random_bytes(3)),
                'zip' => (string)random_int(10000, 99999),
            ];
        }

        return $data;
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
