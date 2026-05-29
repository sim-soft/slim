<?php

declare(strict_types=1);

namespace Simsoft\Resource;

/**
 * Sentinel carrying fields to merge into the parent array.
 *
 * Used by the mergeWhen() helper to signal that the contained
 * fields should be merged into the top-level output array.
 */
final class MergeValue
{
    /**
     * @param array<string, mixed> $fields The fields to merge into the parent array.
     */
    public function __construct(
        public readonly array $fields
    )
    {
    }
}
