<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker\Support;

/**
 * Typed reads of the rows of a stored table setting, which may predate the rules or be written by hand.
 */
final class Rows
{
    /**
     * The rows of a table setting that are arrays.
     *
     * @param array<array-key, mixed> $values
     *
     * @return list<array<array-key, mixed>>
     */
    public static function of(array $values, string $key): array
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
