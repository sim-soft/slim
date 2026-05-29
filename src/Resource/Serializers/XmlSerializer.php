<?php

declare(strict_types=1);

namespace Simsoft\Resource\Serializers;

use Simsoft\Resource\Exceptions\SerializationException;
use Simsoft\Resource\Resource;
use Simsoft\Resource\ResourceCollection;

/**
 * XML serializer for resources.
 *
 * Converts the toSerializedArray() output into well-formed XML 1.0 with
 * UTF-8 encoding declaration and a root element named <response>.
 * Each top-level envelope key (data, meta, links, type) becomes a direct
 * child element of the root. Nested arrays become child elements,
 * sequential arrays use <item> elements, null values produce empty
 * self-closing elements, and scalar values become text content.
 */
class XmlSerializer implements ResourceSerializerInterface
{
    /**
     * Serialize a Resource or ResourceCollection to XML.
     *
     * @param Resource|ResourceCollection $resource The resource to serialize.
     *
     * @return string The XML string representation.
     *
     * @throws SerializationException When XML generation fails.
     */
    public function serialize(Resource|ResourceCollection $resource): string
    {
        $data = $resource->toSerializedArray();

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><response/>');

        try {
            $this->arrayToXml($data, $xml);
        } catch (\Exception $exception) {
            throw new SerializationException(
                'Failed to serialize resource to XML: ' . $exception->getMessage()
            );
        }

        $result = $xml->asXML();

        if ($result === false) {
            throw new SerializationException('Failed to serialize resource to XML');
        }

        return $result;
    }

    /**
     * Return the MIME content type for XML.
     *
     * @return string The XML MIME type.
     */
    public function contentType(): string
    {
        return 'application/xml';
    }

    /**
     * Recursively convert an associative array into XML elements.
     *
     * @param array<string|int, mixed> $data The data to convert.
     * @param \SimpleXMLElement $xml The parent XML element to append to.
     *
     * @return void
     */
    private function arrayToXml(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (\is_int($key)) {
                $this->addValue('item', $value, $xml);
                continue;
            }

            $this->addValue($key, $value, $xml);
        }
    }

    /**
     * Add a single value as an XML child element.
     *
     * @param string $name The element name.
     * @param mixed $value The value to serialize.
     * @param \SimpleXMLElement $xml The parent XML element.
     *
     * @return void
     */
    private function addValue(string $name, mixed $value, \SimpleXMLElement $xml): void
    {
        if ($value === null) {
            $xml->addChild($name);
            return;
        }

        if (\is_array($value)) {
            $child = $xml->addChild($name);
            $this->arrayToXml($value, $child);
            return;
        }

        if (\is_bool($value)) {
            $xml->addChild($name, $value ? 'true' : 'false');
            return;
        }

        $xml->addChild($name, htmlspecialchars((string)$value, ENT_XML1, 'UTF-8'));
    }
}
