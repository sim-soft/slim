<?php

declare(strict_types=1);

namespace Simsoft\Resource;

/**
 * Sentinel indicating a field should be excluded from output.
 *
 * Used internally by conditional helpers (when, whenNotNull, mergeWhen)
 * to signal that a field key should be removed from the serialized array.
 */
final class MissingValue
{
}
