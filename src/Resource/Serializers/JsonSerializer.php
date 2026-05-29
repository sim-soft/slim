<?php

declare(strict_types=1);

namespace Simsoft\Resource\Serializers;

use Simsoft\Resource\Exceptions\SerializationException;
use Simsoft\Resource\Resource;
use Simsoft\Resource\ResourceCollection;

/**
 * Default JSON serializer for resources.
 *
 * Serializes the full envelope structure using json_encode with
 * JSON_PRETTY_PRINT and JSON_UNESCAPED_SLASHES flags.
 */
class JsonSerializer implements ResourceSerializerInterface
{
    /**
     * Serialize a Resource or ResourceCollection to JSON.
     *
     * @param Resource|ResourceCollection $resource The resource to serialize.
     *
     * @return string The JSON string representation.
     *
     * @throws SerializationException When json_encode fails.
     */
    public function serialize(Resource|ResourceCollection $resource): string
    {
        $result = json_encode(
            $resource->toSerializedArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        if ($result === false) {
            throw new SerializationException('Failed to serialize resource to JSON');
        }

        return $result;
    }

    /**
     * Return the MIME content type for JSON.
     *
     * @return string The JSON MIME type.
     */
    public function contentType(): string
    {
        return 'application/json';
    }
}
