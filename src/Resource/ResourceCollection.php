<?php

declare(strict_types=1);

namespace Simsoft\Resource;

use Simsoft\Resource\Exceptions\InvalidHeaderException;
use Simsoft\Resource\Exceptions\InvalidResourceException;

/**
 * Transforms a collection of data items using a specified Resource class.
 *
 * Provides a framework-independent collection transformer that iterates
 * over data items, instantiates the configured Resource subclass for each,
 * and produces a structured JSON-serializable envelope with support for
 * pagination, metadata, links, headers, and field filtering.
 */
class ResourceCollection implements \JsonSerializable
{
    /** @var string The key name used for the data envelope wrapper. */
    protected string $wrapKey = 'data';

    /** @var string|null Optional type identifier included in the envelope. */
    protected ?string $type = null;

    /** @var array<string, mixed> Arbitrary context data propagated to item resources. */
    protected array $context = [];

    /** @var array<string, mixed> Additional metadata for the response envelope. */
    private array $meta = [];

    /** @var array<string, string> HATEOAS link relations for the response envelope. */
    private array $links = [];

    /** @var array<string, string|string[]> Custom HTTP headers to attach to the response. */
    private array $headers = [];

    /** @var string[]|null Field inclusion filter list. */
    private ?array $onlyFields = null;

    /** @var string[]|null Field exclusion filter list. */
    private ?array $exceptFields = null;

    /** @var array{total: int, per_page: int, current_page: int, last_page: int}|null */
    private ?array $pagination = null;

    /**
     * Create a new ResourceCollection instance.
     *
     * @param iterable<mixed> $items The collection of data items to transform.
     * @param string $resourceClass The fully-qualified class name of the Resource subclass.
     *
     * @throws InvalidResourceException When the class does not exist or is not a valid Resource subclass.
     */
    public function __construct(
        private readonly iterable $items,
        private readonly string   $resourceClass
    )
    {
        if (!class_exists($this->resourceClass)) {
            throw new InvalidResourceException(
                "Class '{$this->resourceClass}' is not a valid Resource subclass"
            );
        }

        if ($this->resourceClass === Resource::class) {
            throw new InvalidResourceException(
                "Class '{$this->resourceClass}' is not a valid Resource subclass"
            );
        }

        if (!is_subclass_of($this->resourceClass, Resource::class)) {
            throw new InvalidResourceException(
                "Class '{$this->resourceClass}' is not a valid Resource subclass"
            );
        }
    }

    /**
     * Attach pagination metadata to the collection.
     *
     * @param int $total Total number of items across all pages (minimum 0).
     * @param int $perPage Number of items per page (minimum 1).
     * @param int $currentPage Current page number (minimum 1).
     * @param int $lastPage Last page number (minimum 1).
     *
     * @return static The current collection instance for fluent chaining.
     *
     * @throws \InvalidArgumentException When pagination values are below their minimums.
     */
    public function paginate(int $total, int $perPage, int $currentPage, int $lastPage): static
    {
        if ($total < 0) {
            throw new \InvalidArgumentException('Total must be >= 0');
        }

        if ($perPage < 1) {
            throw new \InvalidArgumentException('Per page must be >= 1');
        }

        if ($currentPage < 1) {
            throw new \InvalidArgumentException('Current page must be >= 1');
        }

        if ($lastPage < 1) {
            throw new \InvalidArgumentException('Last page must be >= 1');
        }

        $this->pagination = [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'last_page' => $lastPage,
        ];

        return $this;
    }

    /**
     * Merge additional context data into this collection.
     *
     * Context is propagated to each item Resource during transformation.
     *
     * @param array<string, mixed> $context Associative array of context data to merge.
     *
     * @return static The current collection instance for fluent chaining.
     */
    public function withContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Merge additional metadata into this collection.
     *
     * Metadata appears under the "meta" key in the response envelope.
     *
     * @param array<string, mixed> $meta Associative array of metadata to merge.
     *
     * @return static The current collection instance for fluent chaining.
     */
    public function withMeta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Merge additional HATEOAS links into this collection.
     *
     * Links appear under the "links" key in the response envelope.
     *
     * @param array<string, string> $links Associative array of link relations to merge.
     *
     * @return static The current collection instance for fluent chaining.
     */
    public function withLinks(array $links): static
    {
        $this->links = array_merge($this->links, $links);

        return $this;
    }

    /**
     * Merge custom HTTP headers into this collection.
     *
     * Headers are applied to the PSR-7 response by the Response adapter.
     * Duplicate header names are resolved using case-insensitive comparison,
     * with later values overwriting earlier values.
     *
     * @param array<string, string|string[]> $headers Associative array of header name to value pairs.
     *
     * @return static The current collection instance for fluent chaining.
     *
     * @throws InvalidHeaderException When a header name is an empty string.
     */
    public function withHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            if ((string)$name === '') {
                throw new InvalidHeaderException('Header name must not be empty');
            }
        }

        $normalized = [];
        foreach ($this->headers as $existingName => $existingValue) {
            $normalized[strtolower($existingName)] = ['name' => $existingName, 'value' => $existingValue];
        }

        foreach ($headers as $name => $value) {
            $lowerName = strtolower((string)$name);
            $normalized[$lowerName] = ['name' => (string)$name, 'value' => $value];
        }

        $this->headers = [];
        foreach ($normalized as $entry) {
            $this->headers[$entry['name']] = $entry['value'];
        }

        return $this;
    }

    /**
     * Set the field inclusion filter.
     *
     * Only the specified fields will be included in each item's serialized output.
     * Calling this method clears any previously set exclusion filter.
     *
     * @param string|array<string> ...$fields Field names as variadic strings or arrays of strings.
     *
     * @return static The current collection instance for fluent chaining.
     */
    public function only(string|array ...$fields): static
    {
        $this->onlyFields = $this->normalizeFields($fields);
        $this->exceptFields = null;

        return $this;
    }

    /**
     * Set the field exclusion filter.
     *
     * The specified fields will be excluded from each item's serialized output.
     * Calling this method clears any previously set inclusion filter.
     *
     * @param string|array<string> ...$fields Field names as variadic strings or arrays of strings.
     *
     * @return static The current collection instance for fluent chaining.
     */
    public function except(string|array ...$fields): static
    {
        $this->exceptFields = $this->normalizeFields($fields);
        $this->onlyFields = null;

        return $this;
    }

    /**
     * Get the custom HTTP headers attached to this collection.
     *
     * @return array<string, string|string[]> The headers array.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Transform all collection items into an array of transformed data.
     *
     * @return array<int, array<string, mixed>> The sequential array of transformed items.
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->items as $item) {
            /** @var Resource $resource */
            $resource = new ($this->resourceClass)($item);

            if ($this->context !== []) {
                $resource->withContext($this->context);
            }

            if ($this->onlyFields !== null) {
                $resource->only(...$this->onlyFields);
            } elseif ($this->exceptFields !== null) {
                $resource->except(...$this->exceptFields);
            }

            $data = $resource->toArray();

            // Apply field filtering after toArray()
            if ($this->onlyFields !== null) {
                $data = array_intersect_key($data, array_flip($this->onlyFields));
            } elseif ($this->exceptFields !== null) {
                $data = array_diff_key($data, array_flip($this->exceptFields));
            }

            $result[] = $data;
        }

        return $result;
    }

    /**
     * Serialize the collection for JSON encoding.
     *
     * Builds the response envelope with type, data, meta, and links.
     * Always recomputes (no caching).
     *
     * @return array<string, mixed> The JSON-serializable representation.
     */
    public function jsonSerialize(): array
    {
        $transformedItems = $this->toArray();

        $envelope = [];

        $trimmedType = $this->type !== null ? trim($this->type) : '';
        if ($trimmedType !== '') {
            $envelope['type'] = $trimmedType;
        }

        $envelope[$this->wrapKey] = $transformedItems;

        // Build meta: merge pagination + additional meta (additional takes precedence)
        $mergedMeta = [];
        if ($this->pagination !== null) {
            $mergedMeta = $this->pagination;
        }

        if ($this->meta !== []) {
            $mergedMeta = array_merge($mergedMeta, $this->meta);
        }

        if ($mergedMeta !== []) {
            $envelope['meta'] = $mergedMeta;
        }

        if ($this->links !== []) {
            $envelope['links'] = $this->links;
        }

        return $envelope;
    }

    /**
     * Return the full envelope structure as a PHP array.
     *
     * Provides the same output as jsonSerialize() but with a guaranteed
     * array return type, suitable for consumption by format serializers.
     *
     * @return array<string, mixed> The full envelope structure.
     */
    public function toSerializedArray(): array
    {
        return $this->jsonSerialize();
    }

    /**
     * Normalize variadic field arguments into a flat string array.
     *
     * @param array<string|array<string>> $fields The variadic arguments to normalize.
     *
     * @return string[] A flat array of field name strings.
     */
    private function normalizeFields(array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            if (\is_array($field)) {
                foreach ($field as $item) {
                    $result[] = $item;
                }
                continue;
            }

            $result[] = $field;
        }

        return $result;
    }
}
