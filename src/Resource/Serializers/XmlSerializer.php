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
     * Matches a valid XML 1.0 element name (the `Name` production, restricted
     * to the common ASCII/Unicode-letter subset and excluding the reserved
     * `xml` prefix).
     *
     * Keys that do not match cannot be used as element names and are emitted
     * as `<item name="...">` instead.
     */
    private const VALID_NAME = '/^(?!(?i:xml))[A-Za-z_][A-Za-z0-9._-]*$/';

    /** @var string Element name used for keys that are not valid XML names. */
    private const FALLBACK_ELEMENT = 'item';

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
        // Keys are frequently derived from database columns or user input and
        // are not guaranteed to be legal XML names. Fall back to
        // <item name="original key"> rather than emitting malformed XML.
        $originalName = $name;
        $isValidName = \preg_match(self::VALID_NAME, $name) === 1;

        if (!$isValidName) {
            $name = self::FALLBACK_ELEMENT;
        }

        $child = $xml->addChild($name, $this->scalarToString($value));

        if (\is_array($value)) {
            $this->arrayToXml($value, $child);
        }

        if (!$isValidName && $child !== null) {
            $child->addAttribute('name', $originalName);
        }
    }

    /**
     * Convert a value to its XML text content.
     *
     * Nulls and arrays carry no text of their own: nulls produce an empty
     * element and arrays are populated from their children by the caller.
     *
     * @param mixed $value The value to convert.
     *
     * @return string|null The escaped text content, or null for no content.
     */
    private function scalarToString(mixed $value): ?string
    {
        if ($value === null || \is_array($value)) {
            return null;
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return htmlspecialchars((string)$value, ENT_XML1, 'UTF-8');
    }
}
