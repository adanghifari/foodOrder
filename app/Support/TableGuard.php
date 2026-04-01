<?php

namespace App\Support;

class TableGuard
{
    public static function isKnownTable(int $tableId): bool
    {
        if ($tableId < 1) {
            return false;
        }

        $knownTableIds = config('tables.known_table_ids');
        if (is_array($knownTableIds) && count($knownTableIds) > 0) {
            return in_array($tableId, $knownTableIds, true);
        }

        $min = (int) config('tables.min_table_id', 1);
        $max = (int) config('tables.max_table_id', 100);

        return $tableId >= $min && $tableId <= $max;
    }
}
