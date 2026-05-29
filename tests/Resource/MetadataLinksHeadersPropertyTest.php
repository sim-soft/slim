<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Resource;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Resource\Exceptions\InvalidHeaderException;
use Simsoft\Resource\Resource;

/**
 * Property-based tests for metadata merge, links placement, and header deduplication.
 *
 * @SuppressWarnings(PHPMD)
 */
class MetadataLinksHeadersPropertyTest extends TestCase
{
    /**
     * Feature: api-resource, Property 7: Metadata merge semantics
     *
     * For any sequence of withMeta() calls, the Resource shallow-merges them
     * using last-value-wins. When non-empty and wrapping enabled, appears under
     * `meta` key. When wrapping disabled, merges into top-level.
     *
     * Validates: Requirements 6.2, 6.4, 6.5
     */
    #[Test]
    #[DataProvider('metadataMergeDataProvider')]
    public function metadataMergeSemantics(
        array $resourceData,
        array $metaCalls,
        bool  $wrapEnabled,
    ): void
    {
        if ($wrapEnabled) {
            $resource = new MetaWrappedResource($resourceData);
        } else {
            $resource = new MetaUnwrappedResource($resourceData);
        }

        // Apply all meta calls in sequence
        foreach ($metaCalls as $meta) {
            $resource->withMeta($meta);
        }

        $result = $resource->jsonSerialize();

        // Compute expected merged meta (shallow-merge, last-value-wins)
        $expectedMeta = [];
        foreach ($metaCalls as $meta) {
            $expectedMeta = array_merge($expectedMeta, $meta);
        }

        if ($wrapEnabled) {
            // Property: meta appears under 'meta' key when wrapping enabled
            if ($expectedMeta !== []) {
                $this->assertArrayHasKey('meta', $result);
                $this->assertSame($expectedMeta, $result['meta']);
            } else {
                $this->assertArrayNotHasKey('meta', $result);
            }
            // Data is still under wrapKey
            $this->assertArrayHasKey('data', $result);
            $this->assertSame($resourceData, $result['data']);
        } else {
            // Property: meta merges into top-level when wrapping disabled
            $this->assertArrayNotHasKey('meta', $result);
            foreach ($expectedMeta as $key => $value) {
                $this->assertArrayHasKey($key, $result);
                $this->assertSame($value, $result[$key]);
            }
            // Original data fields still present
            foreach ($resourceData as $key => $value) {
                if (!array_key_exists($key, $expectedMeta)) {
                    $this->assertArrayHasKey($key, $result);
                    $this->assertSame($value, $result[$key]);
                }
            }
        }
    }

    /**
     * Feature: api-resource, Property 9: Links envelope placement
     *
     * For any non-empty links array via withLinks(), appears under `links` key
     * when wrapping enabled, or `_links` when disabled. Multiple calls shallow-merge.
     *
     * Validates: Requirements 9.2, 9.6, 9.7
     */
    #[Test]
    #[DataProvider('linksPlacementDataProvider')]
    public function linksEnvelopePlacement(
        array $resourceData,
        array $linksCalls,
        bool  $wrapEnabled,
    ): void
    {
        if ($wrapEnabled) {
            $resource = new MetaWrappedResource($resourceData);
        } else {
            $resource = new MetaUnwrappedResource($resourceData);
        }

        // Apply all links calls in sequence
        foreach ($linksCalls as $links) {
            $resource->withLinks($links);
        }

        $result = $resource->jsonSerialize();

        // Compute expected merged links (shallow-merge, last-value-wins)
        $expectedLinks = [];
        foreach ($linksCalls as $links) {
            $expectedLinks = array_merge($expectedLinks, $links);
        }

        if ($wrapEnabled) {
            // Property: links appear under 'links' key when wrapping enabled
            if ($expectedLinks !== []) {
                $this->assertArrayHasKey('links', $result);
                $this->assertSame($expectedLinks, $result['links']);
            } else {
                $this->assertArrayNotHasKey('links', $result);
            }
        } else {
            // Property: links appear under '_links' key when wrapping disabled
            if ($expectedLinks !== []) {
                $this->assertArrayHasKey('_links', $result);
                $this->assertSame($expectedLinks, $result['_links']);
            } else {
                $this->assertArrayNotHasKey('_links', $result);
            }
            // No 'links' key at top level when unwrapped
            $this->assertArrayNotHasKey('links', $result);
        }
    }

    /**
     * Feature: api-resource, Property 17: Header merge with case-insensitive deduplication
     *
     * For any sequence of withHeaders() calls, later values overwrite earlier for
     * duplicate names (case-insensitive). Empty string names throw InvalidHeaderException.
     *
     * Validates: Requirements 13.4, 13.6
     */
    #[Test]
    #[DataProvider('headerMergeDataProvider')]
    public function headerMergeWithCaseInsensitiveDeduplication(
        array $headerCalls,
        array $expectedHeaders,
    ): void
    {
        $resource = new MetaWrappedResource(['id' => 1]);

        foreach ($headerCalls as $headers) {
            $resource->withHeaders($headers);
        }

        $resultHeaders = $resource->getHeaders();

        // Property: final headers match expected after case-insensitive merge
        $this->assertCount(count($expectedHeaders), $resultHeaders);

        // Verify each expected header is present (case-insensitive key match)
        $resultLower = [];
        foreach ($resultHeaders as $name => $value) {
            $resultLower[strtolower($name)] = $value;
        }

        foreach ($expectedHeaders as $name => $value) {
            $this->assertArrayHasKey(strtolower($name), $resultLower);
            $this->assertSame($value, $resultLower[strtolower($name)]);
        }
    }

    /**
     * Feature: api-resource, Property 17: Empty header name throws exception
     *
     * Validates: Requirements 13.6
     */
    #[Test]
    #[DataProvider('emptyHeaderNameDataProvider')]
    public function emptyHeaderNameThrowsException(array $headers): void
    {
        $resource = new MetaWrappedResource(['id' => 1]);

        $this->expectException(InvalidHeaderException::class);
        $resource->withHeaders($headers);
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: array<int, array<string, mixed>>, 2: bool}>
     */
    public static function metadataMergeDataProvider(): array
    {
        $cases = [];

        for ($ii = 0; $ii < 100; $ii++) {
            $resourceData = self::randomAssocArray('field');
            $numCalls = random_int(1, 4);
            $metaCalls = [];
            for ($jj = 0; $jj < $numCalls; $jj++) {
                $metaCalls[] = self::randomMetaArray($jj);
            }
            $wrapEnabled = $ii % 2 === 0;

            // Ensure some overlapping keys to test last-value-wins
            if ($ii % 3 === 0 && $numCalls >= 2) {
                $sharedKey = 'shared_meta';
                $metaCalls[0][$sharedKey] = 'first_' . $ii;
                $metaCalls[$numCalls - 1][$sharedKey] = 'last_' . $ii;
            }

            $cases[] = [$resourceData, $metaCalls, $wrapEnabled];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: array<int, array<string, string>>, 2: bool}>
     */
    public static function linksPlacementDataProvider(): array
    {
        $cases = [];
        $linkRelations = ['self', 'next', 'prev', 'first', 'last', 'related', 'parent'];

        for ($ii = 0; $ii < 100; $ii++) {
            $resourceData = self::randomAssocArray('field');
            $numCalls = random_int(1, 3);
            $linksCalls = [];
            for ($jj = 0; $jj < $numCalls; $jj++) {
                $numLinks = random_int(1, 3);
                $links = [];
                $selectedRels = array_slice($linkRelations, random_int(0, 3), $numLinks);
                foreach ($selectedRels as $rel) {
                    $links[$rel] = '/api/' . bin2hex(random_bytes(3));
                }
                $linksCalls[] = $links;
            }
            $wrapEnabled = $ii % 2 === 0;

            // Ensure some overlapping keys to test last-value-wins
            if ($ii % 4 === 0 && $numCalls >= 2) {
                $linksCalls[0]['self'] = '/api/old';
                $linksCalls[$numCalls - 1]['self'] = '/api/new';
            }

            $cases[] = [$resourceData, $linksCalls, $wrapEnabled];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: array<int, array<string, string|string[]>>, 1: array<string, string|string[]>}>
     */
    public static function headerMergeDataProvider(): array
    {
        $cases = [];
        $headerNames = [
            'X-Request-Id', 'X-Rate-Limit', 'Cache-Control',
            'X-Custom', 'Authorization', 'Accept', 'X-Trace-Id',
        ];

        for ($ii = 0; $ii < 100; $ii++) {
            $numCalls = random_int(1, 4);
            $headerCalls = [];
            $expectedMerged = [];

            for ($jj = 0; $jj < $numCalls; $jj++) {
                $numHeaders = random_int(1, 3);
                $headers = [];
                for ($kk = 0; $kk < $numHeaders; $kk++) {
                    $name = $headerNames[array_rand($headerNames)];
                    // Randomly vary case to test case-insensitive dedup
                    if (random_int(0, 1) === 1) {
                        $name = strtoupper($name);
                    }
                    $value = bin2hex(random_bytes(4));
                    $headers[$name] = $value;
                }
                $headerCalls[] = $headers;
            }

            // Compute expected: simulate case-insensitive merge
            $normalized = [];
            foreach ($headerCalls as $headers) {
                foreach ($headers as $name => $value) {
                    $lowerName = strtolower($name);
                    $normalized[$lowerName] = ['name' => $name, 'value' => $value];
                }
            }

            $expectedMerged = [];
            foreach ($normalized as $entry) {
                $expectedMerged[$entry['name']] = $entry['value'];
            }

            $cases[] = [$headerCalls, $expectedMerged];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: array<string, string>}>
     */
    public static function emptyHeaderNameDataProvider(): array
    {
        $cases = [];

        for ($ii = 0; $ii < 100; $ii++) {
            $headers = ['' => 'some-value-' . $ii];
            // Add some valid headers too
            if ($ii % 2 === 0) {
                $headers['X-Valid'] = 'valid-' . $ii;
            }
            $cases[] = [$headers];
        }

        return $cases;
    }

    /**
     * @return array<string, mixed>
     */
    private static function randomAssocArray(string $prefix): array
    {
        $numFields = random_int(1, 4);
        $result = [];

        for ($ii = 0; $ii < $numFields; $ii++) {
            $result[$prefix . '_' . $ii] = self::randomScalar();
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private static function randomMetaArray(int $index): array
    {
        $keys = ['timestamp', 'version', 'count', 'source', 'tag', 'env', 'region'];
        $numFields = random_int(1, 3);
        $result = [];
        $offset = ($index * 2) % count($keys);
        $selectedKeys = array_slice($keys, $offset, $numFields);

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

/**
 * Wrapped resource for metadata/links property testing.
 */
class MetaWrappedResource extends Resource
{
    public function toArray(): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        return (array)$this->resource;
    }
}

/**
 * Unwrapped resource for metadata/links property testing.
 */
class MetaUnwrappedResource extends Resource
{
    protected bool $wrap = false;

    public function toArray(): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        return (array)$this->resource;
    }
}
