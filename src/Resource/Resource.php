<?php

declare(strict_types=1);

namespace Simsoft\Resource;

/**
 * Abstract base resource for transforming data items into structured API responses.
 *
 * Provides a framework-independent data transformation layer that converts
 * raw PHP data (objects, arrays) into structured JSON-serializable output
 * with support for wrapping, metadata, links, conditional fields, and caching.
 */
abstract class Resource implements \JsonSerializable
{
    use ConditionalFieldsTrait;

    // --- Class-level properties (overridable by subclasses) ---

    /** @var bool Whether to wrap the output in a data envelope. */
    protected bool $wrap = true;

    /** @var string The key name used for the data envelope wrapper. */
    protected string $wrapKey = 'data';

    /** @var string|null Optional type identifier included in the envelope. */
    protected ?string $type = null;

    /** @var array<string, string> Declarative field-to-path mapping for auto-transform. */
    protected array $map = [];

    // --- Instance state ---

    /** @var array<string, mixed> Arbitrary context data accessible in toArray(). */
    protected array $context = [];

    /** @var array<string, mixed>|null Cached serialization result. */
    private ?array $cachedResult = null;

    /** @var string[]|null Field inclusion filter list. */
    private ?array $onlyFields = null;

    /** @var string[]|null Field exclusion filter list. */
    private ?array $exceptFields = null;

    /** @var array<string, mixed> Additional metadata for the response envelope. */
    protected array $meta = [];

    /** @var array<string, string> HATEOAS link relations for the response envelope. */
    protected array $links = [];

    /** @var array<string, string|string[]> Custom HTTP headers to attach to the response. */
    private array $headers = [];

    /**
     * Create a new Resource instance.
     *
     * @param object|array<string, mixed> $resource The raw data item to transform.
     */
    public function __construct(
        public readonly object|array $resource
    )
    {
    }

    /**
     * Transform the resource data into an associative array.
     *
     * The default implementation uses the declarative $map property when non-empty,
     * resolving each entry via dot-notation path traversal on the resource data.
     * Subclasses may override this method for custom transformation logic;
     * when overridden, the $map property is ignored.
     *
     * @return array<string, mixed> The transformed data representation.
     */
    public function toArray(): array
    {
        if (!empty($this->map)) {
            return $this->resolveMap();
        }

        return [];
    }

    /**
     * Hook called before toArray() during serialization.
     *
     * Override in subclasses to perform setup work such as lazy-loading
     * relationships or computing derived values. Has access to $this->context.
     *
     * @return void
     */
    protected function beforeSerialize(): void
    {
    }

    /**
     * Hook called after toArray() and field filtering during serialization.
     *
     * Override in subclasses to perform post-processing such as sorting keys,
     * adding computed fields, or removing empty values. Has access to $this->context.
     *
     * @param array<string, mixed> $data The filtered toArray() output.
     *
     * @return array<string, mixed> The final serialization output.
     */
    protected function afterSerialize(array $data): array
    {
        return $data;
    }

    /**
     * Create a ResourceCollection from an iterable using the called class.
     *
     * Uses late static binding so that `UserResource::collection($users)`
     * configures the collection with `UserResource` as the item resource class.
     *
     * @param iterable<mixed> $items The collection of data items to transform.
     *
     * @return ResourceCollection A new collection instance configured with the calling class.
     */
    public static function collection(iterable $items): ResourceCollection
    {
        return new ResourceCollection($items, static::class);
    }

    /**
     * Create a new resource instance from the given data.
     *
     * Returns a NullResource when null is passed, otherwise creates
     * a new instance of the called subclass.
     *
     * @param object|array<string, mixed>|null $data The data item to wrap in a resource.
     *
     * @return self A new resource instance (or NullResource when data is null).
     */
    public static function make(object|array|null $data): self
    {
        if ($data === null) {
            return new NullResource();
        }

        return new static($data); // @phpstan-ignore new.static
    }

    /**
     * Serialize the resource for JSON encoding.
     *
     * Executes the full serialization pipeline: cache check, lifecycle hooks,
     * data resolution, conditional resolution, field filtering, nested resource
     * serialization, envelope wrapping, and result caching.
     *
     * @return array<string, mixed> The JSON-serializable representation.
     */
    public function jsonSerialize(): mixed
    {
        if ($this->cachedResult !== null) {
            return $this->cachedResult;
        }

        $data = $this->processData();

        // Build envelope
        $result = $this->wrap
            ? $this->buildWrappedEnvelope($data)
            : $this->buildUnwrappedEnvelope($data);

        $this->cachedResult = $result;

        return $result;
    }

    /**
     * Build a wrapped envelope with type, data key, meta, and links.
     *
     * @param array<string, mixed> $data The processed data array.
     *
     * @return array<string, mixed> The wrapped envelope.
     */
    private function buildWrappedEnvelope(array $data): array
    {
        $envelope = [];

        $trimmedType = $this->type !== null ? trim($this->type) : '';
        if ($trimmedType !== '') {
            $envelope['type'] = $trimmedType;
        }

        $envelope[$this->wrapKey] = $data;

        if ($this->meta !== []) {
            $envelope['meta'] = $this->meta;
        }

        if ($this->links !== []) {
            $envelope['links'] = $this->links;
        }

        return $envelope;
    }

    /**
     * Build an unwrapped envelope merging meta into top-level and links under _links.
     *
     * @param array<string, mixed> $data The processed data array.
     *
     * @return array<string, mixed> The unwrapped result.
     */
    private function buildUnwrappedEnvelope(array $data): array
    {
        $result = $data;

        if ($this->meta !== []) {
            $result = array_merge($result, $this->meta);
        }

        if ($this->links !== []) {
            $result['_links'] = $this->links;
        }

        return $result;
    }

    /**
     * Execute the full data processing pipeline without envelope wrapping.
     *
     * Runs: beforeSerialize → resolveData → resolve conditionals →
     * apply only/except filters → serialize nested resources → afterSerialize.
     *
     * Used internally by jsonSerialize() for the current resource and
     * by parent resources when serializing nested Resource instances
     * to produce the full pipeline output without an envelope wrapper.
     *
     * @return array<string, mixed> The fully processed data array.
     */
    private function processData(): array
    {
        $this->beforeSerialize();

        $data = $this->toArray();

        // Resolve conditionals: remove MissingValue keys, flatten MergeValue fields
        $resolved = $this->resolveConditionals($data);

        // Apply only/except filtering
        $resolved = $this->applyFieldFilters($resolved);

        // Serialize nested resources and collections
        $resolved = $this->serializeNestedResources($resolved);

        // Post-processing hook
        return $this->afterSerialize($resolved);
    }

    /**
     * Apply only/except field filtering to the resolved data.
     *
     * When onlyFields is set, only matching keys are kept.
     * When exceptFields is set, matching keys are removed.
     * onlyFields takes precedence over exceptFields.
     *
     * @param array<string, mixed> $data The resolved data array.
     *
     * @return array<string, mixed> The filtered data array.
     */
    private function applyFieldFilters(array $data): array
    {
        if ($this->onlyFields !== null) {
            return array_intersect_key($data, array_flip($this->onlyFields));
        }

        if ($this->exceptFields !== null) {
            return array_diff_key($data, array_flip($this->exceptFields));
        }

        return $data;
    }

    /**
     * Serialize nested Resource and ResourceCollection instances within the data.
     *
     * Propagates the parent context to each nested instance before serialization.
     *
     * @param array<string, mixed> $data The filtered data array.
     *
     * @return array<string, mixed> The data array with nested resources serialized.
     */
    private function serializeNestedResources(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof self) {
                $value->withContext($this->context);
                $data[$key] = $value->processData();
                continue;
            }

            if ($value instanceof ResourceCollection) {
                $value->withContext($this->context);
                $data[$key] = $value->toArray();
            }
        }

        return $data;
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
     * Merge additional context data into this resource.
     *
     * Context is accessible within toArray() for conditional logic.
     * Calling this method invalidates any cached serialization result.
     *
     * @param array<string, mixed> $context Associative array of context data to merge.
     *
     * @return static The current resource instance for fluent chaining.
     */
    public function withContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);
        $this->cachedResult = null;

        return $this;
    }

    /**
     * Merge additional metadata into this resource.
     *
     * Metadata appears under the "meta" key in the response envelope.
     *
     * @param array<string, mixed> $meta Associative array of metadata to merge.
     *
     * @return static The current resource instance for fluent chaining.
     */
    public function withMeta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Merge additional HATEOAS links into this resource.
     *
     * Links appear under the "links" key in the response envelope.
     *
     * @param array<string, string> $links Associative array of link relations to merge.
     *
     * @return static The current resource instance for fluent chaining.
     */
    public function withLinks(array $links): static
    {
        $this->links = array_merge($this->links, $links);

        return $this;
    }

    /**
     * Merge custom HTTP headers into this resource.
     *
     * Headers are applied to the PSR-7 response by the Response adapter.
     * Duplicate header names are resolved using case-insensitive comparison,
     * with later values overwriting earlier values.
     *
     * @param array<string, string|string[]> $headers Associative array of header name to value pairs.
     *
     * @return static The current resource instance for fluent chaining.
     *
     * @throws Exceptions\InvalidHeaderException When a header name is an empty string.
     */
    public function withHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            if ((string)$name === '') {
                throw new Exceptions\InvalidHeaderException('Header name must not be empty');
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
     * Only the specified fields will be included in the serialized output.
     * Calling this method clears any previously set exclusion filter.
     *
     * @param string|array<string> ...$fields Field names as variadic strings or arrays of strings.
     *
     * @return static The current resource instance for fluent chaining.
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
     * The specified fields will be excluded from the serialized output.
     * Calling this method clears any previously set inclusion filter.
     *
     * @param string|array<string> ...$fields Field names as variadic strings or arrays of strings.
     *
     * @return static The current resource instance for fluent chaining.
     */
    public function except(string|array ...$fields): static
    {
        $this->exceptFields = $this->normalizeFields($fields);
        $this->onlyFields = null;

        return $this;
    }

    /**
     * Get the custom HTTP headers attached to this resource.
     *
     * @return array<string, string|string[]> The headers array.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Resolve the declarative $map property into an associative array.
     *
     * Iterates over each entry in $this->map, resolving the source path
     * from $this->resource using dot-notation traversal.
     *
     * @return array<string, mixed> The resolved field values keyed by output field name.
     */
    private function resolveMap(): array
    {
        $result = [];
        foreach ($this->map as $outputField => $sourcePath) {
            $result[$outputField] = $this->resolvePathValue($this->resource, $sourcePath);
        }

        return $result;
    }

    /**
     * Resolve a dot-notation path against a target object or array.
     *
     * Splits the path by '.' and traverses each segment. For each segment:
     * - If the current value is an object, access as a property.
     * - If the current value is an array, access as a key.
     * - If any segment is null, missing, or not traversable, returns null.
     *
     * @param object|array<string, mixed> $target The data structure to traverse.
     * @param string $path The dot-notation path (e.g., 'address.city').
     *
     * @return mixed The resolved value, or null if any segment is unresolvable.
     */
    private function resolvePathValue(object|array $target, string $path): mixed
    {
        $segments = explode('.', $path);
        $current = $target;

        foreach ($segments as $segment) {
            if (\is_object($current)) {
                if (!property_exists($current, $segment)) {
                    return null;
                }
                $current = $current->{$segment};
                continue;
            }

            if (\is_array($current)) {
                if (!array_key_exists($segment, $current)) {
                    return null;
                }
                $current = $current[$segment];
                continue;
            }

            return null;
        }

        return $current;
    }

}
