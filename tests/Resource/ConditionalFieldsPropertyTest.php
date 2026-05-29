<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Resource;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Resource\Resource;

/**
 * Property-based tests for conditional field inclusion/exclusion.
 *
 * @SuppressWarnings(PHPMD)
 */
class ConditionalFieldsPropertyTest extends TestCase
{
    /**
     * Feature: api-resource, Property 5: Conditional field inclusion/exclusion
     *
     * For any boolean condition and value:
     * - when(true, value) includes the value
     * - when(false, value) excludes the key entirely
     * - whenNotNull(value) includes when non-null, excludes when null
     * - mergeWhen(true, fields) merges all fields
     * - mergeWhen(false, fields) excludes all
     *
     * Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.5, 4.6
     */
    #[Test]
    #[DataProvider('conditionalFieldsDataProvider')]
    public function conditionalFieldInclusionExclusion(
        bool  $whenCondition,
        mixed $whenValue,
        mixed $whenNotNullValue,
        bool  $mergeCondition,
        array $mergeFields,
    ): void
    {
        $resource = new class (
            ['id' => 1],
            $whenCondition,
            $whenValue,
            $whenNotNullValue,
            $mergeCondition,
            $mergeFields,
        ) extends Resource {
            protected bool $wrap = false;

            public function __construct(
                object|array  $resource,
                private bool  $whenCond,
                private mixed $whenVal,
                private mixed $whenNotNullVal,
                private bool  $mergeCond,
                private array $mergeFields,
            )
            {
                parent::__construct($resource);
            }

            public function toArray(): array
            {
                return [
                    'base' => 'always',
                    'conditional' => $this->when($this->whenCond, $this->whenVal),
                    'nullable' => $this->whenNotNull($this->whenNotNullVal),
                    $this->mergeWhen($this->mergeCond, $this->mergeFields),
                ];
            }
        };

        $result = $resource->jsonSerialize();

        // Property: base field always present
        $this->assertArrayHasKey('base', $result);
        $this->assertSame('always', $result['base']);

        // Property: when(true, value) includes the value
        if ($whenCondition) {
            $this->assertArrayHasKey('conditional', $result);
            $this->assertSame($whenValue, $result['conditional']);
        } else {
            // Property: when(false, value) excludes the key entirely
            $this->assertArrayNotHasKey('conditional', $result);
        }

        // Property: whenNotNull(value) includes when non-null
        if ($whenNotNullValue !== null) {
            $this->assertArrayHasKey('nullable', $result);
            $this->assertSame($whenNotNullValue, $result['nullable']);
        } else {
            // Property: whenNotNull(null) excludes the key
            $this->assertArrayNotHasKey('nullable', $result);
        }

        // Property: mergeWhen(true, fields) merges all fields
        if ($mergeCondition) {
            foreach ($mergeFields as $key => $value) {
                $this->assertArrayHasKey($key, $result);
                $this->assertSame($value, $result[$key]);
            }
        } else {
            // Property: mergeWhen(false, fields) excludes all
            foreach ($mergeFields as $key => $value) {
                if ($key === 'base') {
                    continue; // base is always present from the resource itself
                }
                $this->assertArrayNotHasKey($key, $result);
            }
        }
    }

    /**
     * @return array<int, array{0: bool, 1: mixed, 2: mixed, 3: bool, 4: array<string, mixed>}>
     */
    public static function conditionalFieldsDataProvider(): array
    {
        $cases = [];
        $mergeKeyPool = ['extra', 'bonus', 'detail', 'info', 'tag', 'flag', 'note', 'ref'];

        for ($ii = 0; $ii < 100; $ii++) {
            $whenCondition = random_int(0, 1) === 1;
            $whenValue = self::randomScalar();
            $whenNotNullValue = random_int(0, 1) === 1 ? self::randomScalar() : null;
            $mergeCondition = random_int(0, 1) === 1;

            $numMergeFields = random_int(1, 3);
            $mergeFields = [];
            $selectedKeys = array_slice($mergeKeyPool, 0, $numMergeFields);
            foreach ($selectedKeys as $key) {
                $mergeFields[$key] = self::randomScalar();
            }

            $cases[] = [$whenCondition, $whenValue, $whenNotNullValue, $mergeCondition, $mergeFields];
        }

        return $cases;
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
