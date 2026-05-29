<?php

declare(strict_types=1);

namespace Simsoft\Resource\Serializers;

use Simsoft\Resource\Resource;
use Simsoft\Resource\ResourceCollection;

/**
 * Interface for serializing resources into text-based structured data formats.
 *
 * Implementations convert the full envelope structure (data, meta, links, type)
 * produced by Resource::toSerializedArray() into a specific output format.
 */
interface ResourceSerializerInterface
{
    /**
     * Serialize a Resource or ResourceCollection into a string.
     *
     * Implementations should call toSerializedArray() on the resource to obtain
     * the full envelope structure, then convert it to the target format.
     *
     * @param Resource|ResourceCollection $resource The resource to serialize.
     *
     * @return string The serialized string representation.
     *
     * @throws \Simsoft\Resource\Exceptions\SerializationException When serialization fails.
     */
    public function serialize(Resource|ResourceCollection $resource): string;

    /**
     * Return the MIME content type for this serializer's output format.
     *
     * @return string The MIME type string (e.g., 'application/json', 'application/xml').
     */
    public function contentType(): string;
}
