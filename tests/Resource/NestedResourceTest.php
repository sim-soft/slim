<?php

declare(strict_types=1);

namespace Tests\Resource;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Resource\MissingValue;
use Simsoft\Resource\Resource;
use Simsoft\Resource\ResourceCollection;

/**
 * Tests for nested resource serialization within the jsonSerialize pipeline.
 *
 * Validates Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 8.4, 8.5, 8.10
 */
final class NestedResourceTest extends TestCase
{
    #[Test]
    public function nestedResourceSerializedWithoutEnvelope(): void
    {
        $parent = new class (['name' => 'Parent']) extends Resource {
            public function toArray(): array
            {
                $child = new class (['title' => 'Child']) extends Resource {
                    public function toArray(): array
                    {
                        return ['title' => $this->resource['title']];
                    }
                };

                return [
                    'name' => $this->resource['name'],
                    'child' => $child,
                ];
            }
        };

        $result = $parent->jsonSerialize();

        $this->assertSame(['title' => 'Child'], $result['data']['child']);
    }

    #[Test]
    public function nestedResourceCollectionSerializedWithoutEnvelope(): void
    {
        $childResourceClass = get_class(new class (['x' => 1]) extends Resource {
            public function toArray(): array
            {
                return ['x' => $this->resource['x']];
            }
        });

        $items = [['x' => 1], ['x' => 2], ['x' => 3]];
        $collection = new ResourceCollection($items, $childResourceClass);

        $parent = new class (['name' => 'Parent'], $collection) extends Resource {
            private ResourceCollection $childCollection;

            public function __construct(array $data, ResourceCollection $collection)
            {
                parent::__construct($data);
                $this->childCollection = $collection;
            }

            public function toArray(): array
            {
                return [
                    'name' => $this->resource['name'],
                    'items' => $this->childCollection,
                ];
            }
        };

        $result = $parent->jsonSerialize();

        $this->assertSame(
            [['x' => 1], ['x' => 2], ['x' => 3]],
            $result['data']['items']
        );
    }

    #[Test]
    public function contextPropagatedToNestedResource(): void
    {
        $parent = new class (['name' => 'Parent']) extends Resource {
            public function toArray(): array
            {
                $child = new class (['title' => 'Child']) extends Resource {
                    public function toArray(): array
                    {
                        return [
                            'title' => $this->resource['title'],
                            'role' => $this->context['role'] ?? 'none',
                        ];
                    }
                };

                return [
                    'name' => $this->resource['name'],
                    'child' => $child,
                ];
            }
        };

        $parent->withContext(['role' => 'admin']);
        $result = $parent->jsonSerialize();

        $this->assertSame('admin', $result['data']['child']['role']);
    }

    #[Test]
    public function contextPropagatedToNestedResourceCollection(): void
    {
        $childResourceClass = get_class(new class (['x' => 1]) extends Resource {
            public function toArray(): array
            {
                return [
                    'x' => $this->resource['x'],
                    'env' => $this->context['env'] ?? 'unknown',
                ];
            }
        });

        $items = [['x' => 10]];
        $collection = new ResourceCollection($items, $childResourceClass);

        $parent = new class (['name' => 'P'], $collection) extends Resource {
            private ResourceCollection $childCollection;

            public function __construct(array $data, ResourceCollection $collection)
            {
                parent::__construct($data);
                $this->childCollection = $collection;
            }

            public function toArray(): array
            {
                return [
                    'name' => $this->resource['name'],
                    'items' => $this->childCollection,
                ];
            }
        };

        $parent->withContext(['env' => 'production']);
        $result = $parent->jsonSerialize();

        $this->assertSame('production', $result['data']['items'][0]['env']);
    }

    #[Test]
    public function threeLevelsDeepNesting(): void
    {
        $parent = new class (['id' => 1]) extends Resource {
            public function toArray(): array
            {
                $grandchild = new class (['val' => 'deep']) extends Resource {
                    public function toArray(): array
                    {
                        return ['val' => $this->resource['val']];
                    }
                };

                $child = new class (['mid' => 'level'], $grandchild) extends Resource {
                    private Resource $grandchild;

                    public function __construct(array $data, Resource $gc)
                    {
                        parent::__construct($data);
                        $this->grandchild = $gc;
                    }

                    public function toArray(): array
                    {
                        return [
                            'mid' => $this->resource['mid'],
                            'grandchild' => $this->grandchild,
                        ];
                    }
                };

                return [
                    'id' => $this->resource['id'],
                    'child' => $child,
                ];
            }
        };

        $result = $parent->jsonSerialize();

        $this->assertSame(
            ['mid' => 'level', 'grandchild' => ['val' => 'deep']],
            $result['data']['child']
        );
        $this->assertSame('deep', $result['data']['child']['grandchild']['val']);
    }

    #[Test]
    public function contextPropagatedRecursivelyThroughThreeLevels(): void
    {
        $parent = new class (['id' => 1]) extends Resource {
            public function toArray(): array
            {
                $grandchild = new class (['val' => 'deep']) extends Resource {
                    public function toArray(): array
                    {
                        return [
                            'val' => $this->resource['val'],
                            'ctx' => $this->context['shared'] ?? 'missing',
                        ];
                    }
                };

                $child = new class (['mid' => 'level'], $grandchild) extends Resource {
                    private Resource $grandchild;

                    public function __construct(array $data, Resource $gc)
                    {
                        parent::__construct($data);
                        $this->grandchild = $gc;
                    }

                    public function toArray(): array
                    {
                        return [
                            'mid' => $this->resource['mid'],
                            'grandchild' => $this->grandchild,
                        ];
                    }
                };

                return [
                    'id' => $this->resource['id'],
                    'child' => $child,
                ];
            }
        };

        $parent->withContext(['shared' => 'propagated']);
        $result = $parent->jsonSerialize();

        $this->assertSame('propagated', $result['data']['child']['grandchild']['ctx']);
    }

    #[Test]
    public function scalarAndPlainArrayValuesPassedThrough(): void
    {
        $parent = new class (['name' => 'Test']) extends Resource {
            public function toArray(): array
            {
                return [
                    'name' => $this->resource['name'],
                    'count' => 42,
                    'tags' => ['php', 'api'],
                    'active' => true,
                    'score' => 3.14,
                ];
            }
        };

        $result = $parent->jsonSerialize();

        $this->assertSame('Test', $result['data']['name']);
        $this->assertSame(42, $result['data']['count']);
        $this->assertSame(['php', 'api'], $result['data']['tags']);
        $this->assertTrue($result['data']['active']);
        $this->assertSame(3.14, $result['data']['score']);
    }

    #[Test]
    public function nullNestedResourceFieldIncludedAsNull(): void
    {
        $parent = new class (['name' => 'Test']) extends Resource {
            public function toArray(): array
            {
                return [
                    'name' => $this->resource['name'],
                    'optional' => null,
                ];
            }
        };

        $result = $parent->jsonSerialize();

        $this->assertArrayHasKey('optional', $result['data']);
        $this->assertNull($result['data']['optional']);
    }

    #[Test]
    public function conditionalFieldResolvingToResourceIsSerializedCorrectly(): void
    {
        $parent = new class (['name' => 'Test']) extends Resource {
            public function toArray(): array
            {
                $child = new class (['title' => 'Conditional']) extends Resource {
                    public function toArray(): array
                    {
                        return ['title' => $this->resource['title']];
                    }
                };

                return [
                    'name' => $this->resource['name'],
                    'detail' => $this->when(true, $child),
                ];
            }
        };

        $result = $parent->jsonSerialize();

        $this->assertSame(['title' => 'Conditional'], $result['data']['detail']);
    }

    #[Test]
    public function conditionalFieldResolvingToResourceCollectionIsSerializedCorrectly(): void
    {
        $childResourceClass = get_class(new class (['v' => 1]) extends Resource {
            public function toArray(): array
            {
                return ['v' => $this->resource['v']];
            }
        });

        $collection = new ResourceCollection([['v' => 5], ['v' => 6]], $childResourceClass);

        $parent = new class (['name' => 'Test'], $collection) extends Resource {
            private ResourceCollection $coll;

            public function __construct(array $data, ResourceCollection $collection)
            {
                parent::__construct($data);
                $this->coll = $collection;
            }

            public function toArray(): array
            {
                return [
                    'name' => $this->resource['name'],
                    'list' => $this->when(true, $this->coll),
                ];
            }
        };

        $result = $parent->jsonSerialize();

        $this->assertSame([['v' => 5], ['v' => 6]], $result['data']['list']);
    }

    #[Test]
    public function nestedResourceRunsFullPipelineIncludingConditionals(): void
    {
        $parent = new class (['name' => 'Parent']) extends Resource {
            public function toArray(): array
            {
                $child = new class (['title' => 'Child', 'secret' => 'hidden']) extends Resource {
                    public function toArray(): array
                    {
                        return [
                            'title' => $this->resource['title'],
                            'secret' => $this->when(false, $this->resource['secret']),
                        ];
                    }
                };

                return [
                    'name' => $this->resource['name'],
                    'child' => $child,
                ];
            }
        };

        $result = $parent->jsonSerialize();

        // The child's conditional should be resolved - 'secret' excluded
        $this->assertSame(['title' => 'Child'], $result['data']['child']);
    }

    #[Test]
    public function nestedResourceRunsAfterSerializeHook(): void
    {
        $parent = new class (['name' => 'Parent']) extends Resource {
            public function toArray(): array
            {
                $child = new class (['title' => 'Child']) extends Resource {
                    public function toArray(): array
                    {
                        return ['title' => $this->resource['title']];
                    }

                    protected function afterSerialize(array $data): array
                    {
                        $data['added'] = 'by_hook';

                        return $data;
                    }
                };

                return [
                    'name' => $this->resource['name'],
                    'child' => $child,
                ];
            }
        };

        $result = $parent->jsonSerialize();

        $this->assertSame('by_hook', $result['data']['child']['added']);
    }

    #[Test]
    public function excludedNestedResourceNotSerialized(): void
    {
        $callCount = 0;

        $parent = new class (['name' => 'Parent'], $callCount) extends Resource {
            /** @var int */
            private int $counter;

            public function __construct(array $data, int &$counter)
            {
                parent::__construct($data);
                $this->counter = &$counter;
            }

            public function toArray(): array
            {
                $counter = &$this->counter;
                $child = new class (['title' => 'Child'], $counter) extends Resource {
                    /** @var int */
                    private int $counter;

                    public function __construct(array $data, int &$counter)
                    {
                        parent::__construct($data);
                        $this->counter = &$counter;
                    }

                    public function toArray(): array
                    {
                        $this->counter++;

                        return ['title' => $this->resource['title']];
                    }
                };

                return [
                    'name' => $this->resource['name'],
                    'child' => $child,
                ];
            }
        };

        $parent->only('name');
        $result = $parent->jsonSerialize();

        $this->assertArrayNotHasKey('child', $result['data']);
        // The child's toArray should not have been called since it was filtered out
        // Note: The child resource is created in parent's toArray, but processData
        // on the child should not be called since it's excluded by only filter
        $this->assertSame(0, $callCount);
    }
}
