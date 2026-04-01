<?php

namespace App\Services;

use App\Models\Order;
use App\Support\TableGuard;
use Illuminate\Http\Request;

class TableService
{
    public function isKnownTable(int $tableId): bool
    {
        return TableGuard::isKnownTable($tableId);
    }

    public function isTableAvailable(int $tableId): bool
    {
        return !Order::where('table_number', $tableId)
            ->whereIn('status', ['CONFIRMED', 'IN_QUEUE', 'IN_PROGRESS'])
            ->exists();
    }

    public function clearTableSession(Request $request): bool
    {
        if (!$request->hasSession()) {
            return false;
        }

        $request->session()->forget('table_id');
        return true;
    }

    public function storeTableSession(Request $request, int $tableId): void
    {
        $request->session()->put('table_id', $tableId);
    }
}
