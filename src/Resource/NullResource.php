<?php

declare(strict_types=1);

namespace Simsoft\Resource;

/**
 * Null object resource representing the absence of data.
 *
 * Produces `{"data": null}` when wrapping is enabled, or the JSON literal
 * `null` when wrapping is disabled. Metadata and links are included in the
 * envelope when wrapping is enabled, but discarded when wrapping is disabled.
 */
class NullResource extends Resource
{
    /**
     * Create a new NullResource instance.
     *
     * Passes an empty array to the parent constructor to satisfy the
     * readonly `resource` property requirement, since NullResource
     * represents the absence of data.
     */
    public function __construct()
    {
        parent::__construct([]);
    }

    /**
     * Transform the resource data into an array.
     *
     * Returns an empty array since NullResource has no data to transform.
     * The actual null representation is handled by jsonSerialize().
     *
     * @return array<string, mixed> An empty array.
     */
    public function toArray(): array
    {
        return [];
    }

    /**
     * Serialize the NullResource for JSON encoding.
     *
     * When wrapping is enabled, produces an envelope with null as the data
     * value, including any metadata and links. When wrapping is disabled,
     * produces null and discards metadata and links.
     *
     * @return array<string, mixed>|null The JSON-serializable representation.
     */
    public function jsonSerialize(): mixed
    {
        if (!$this->wrap) {
            return null;
        }

        $envelope = [];

        $trimmedType = $this->type !== null ? trim($this->type) : '';
        if ($trimmedType !== '') {
            $envelope['type'] = $trimmedType;
        }

        $envelope[$this->wrapKey] = null;

        if ($this->meta !== []) {
            $envelope['meta'] = $this->meta;
        }

        if ($this->links !== []) {
            $envelope['links'] = $this->links;
        }

        return $envelope;
    }
}
