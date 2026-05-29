<?php

declare(strict_types=1);

namespace Simsoft\Resource;

/**
 * Provides conditional field inclusion helpers for resource serialization.
 *
 * Used by the Resource class to support when(), whenNotNull(), and mergeWhen()
 * conditional field resolution during the serialization pipeline.
 */
trait ConditionalFieldsTrait
{
    /**
     * Include a value in the output only when the condition is true.
     *
     * When the condition is true and the value is a Closure, the Closure is
     * evaluated and its return value is used. When the condition is false,
     * a MissingValue sentinel is returned to signal field exclusion.
     *
     * @param bool $condition The condition to evaluate.
     * @param mixed $value The value to include, or a Closure that produces it.
     *
     * @return mixed The resolved value when condition is true, or MissingValue when false.
     */
    protected function when(bool $condition, mixed $value): mixed
    {
        if (!$condition) {
            return new MissingValue();
        }

        if ($value instanceof \Closure) {
            return $value();
        }

        return $value;
    }

    /**
     * Include a value in the output only when it is not null.
     *
     * Returns the value as-is when non-null, or a MissingValue sentinel
     * to signal field exclusion when the value is null.
     *
     * @param mixed $value The value to check for null.
     *
     * @return mixed The value when non-null, or MissingValue when null.
     */
    protected function whenNotNull(mixed $value): mixed
    {
        if ($value === null) {
            return new MissingValue();
        }

        return $value;
    }

    /**
     * Merge an array of fields into the output only when the condition is true.
     *
     * Returns a MergeValue sentinel carrying the fields when the condition is true,
     * or a MissingValue sentinel to signal exclusion when the condition is false.
     *
     * @param bool $condition The condition to evaluate.
     * @param array<string, mixed> $fields The fields to merge into the parent array.
     *
     * @return MergeValue|MissingValue MergeValue when true, MissingValue when false.
     */
    protected function mergeWhen(bool $condition, array $fields): MergeValue|MissingValue
    {
        if (!$condition) {
            return new MissingValue();
        }

        return new MergeValue($fields);
    }

    /**
     * Resolve conditional sentinel values in the data array.
     *
     * Removes MissingValue entries and flattens MergeValue fields into the parent array.
     *
     * @param array<string, mixed> $data The raw data array with potential sentinels.
     *
     * @return array<string, mixed> The resolved data array.
     */
    private function resolveConditionals(array $data): array
    {
        $resolved = [];
        foreach ($data as $key => $value) {
            if ($value instanceof MissingValue) {
                continue;
            }

            if ($value instanceof MergeValue) {
                foreach ($value->fields as $mergeKey => $mergeVal) {
                    $resolved[$mergeKey] = $mergeVal;
                }
                continue;
            }

            $resolved[$key] = $value;
        }

        return $resolved;
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
