<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker\Support;

/**
 * Typed reads of stored settings, which may predate the rules or be written by hand.
 */
final class Values
{
    /**
     * @param array<array-key, mixed> $values
     */
    public static function bool(array $values, string $key, bool $default = false): bool
    {
        $value = $values[$key] ?? $default;

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public static function int(array $values, string $key, int $default, int $min, int $max): int
    {
        $value = $values[$key] ?? null;

        return is_numeric($value) ? max($min, min($max, (int)$value)) : $default;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public static function string(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? null;

        return is_scalar($value) ? trim((string)$value) : $default;
    }

    /**
     * The rows of a table setting that are arrays.
     *
     * @param array<array-key, mixed> $values
     *
     * @return list<array<array-key, mixed>>
     */
    public static function rows(array $values, string $key): array
    {
        $rows = $values[$key] ?? [];

        return array_values(array_filter(is_array($rows) ? $rows : [], is_array(...)));
    }

    /**
     * One trimmed text column of a row, empty when it is not text.
     *
     * @param array<array-key, mixed> $row
     */
    public static function text(array $row, string $column): string
    {
        return is_string($row[$column] ?? null) ? trim($row[$column]) : '';
    }
}
