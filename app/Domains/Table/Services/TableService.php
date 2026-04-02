<?php

namespace App\Domains\Table\Services;

use App\Models\Order;
use App\Support\TableGuard;
use Illuminate\Http\Request;

class TableService
{
    private const ACTIVE_ORDER_STATUSES = ['CONFIRMED', 'IN_QUEUE', 'IN_PROGRESS'];

    public function isKnownTable(int $tableId): bool
    {
        return TableGuard::isKnownTable($tableId);
    }

    public function isTableAvailable(int $tableId): bool
    {
        return !Order::where('table_number', $tableId)
            ->whereIn('status', self::ACTIVE_ORDER_STATUSES)
            ->exists();
    }

    public function clearTableSessionIfInactive(Request $request): bool
    {
        if (!$request->hasSession()) {
            return false;
        }

        $tableId = $request->session()->get('table_id');
        $sessionStartedAt = $request->session()->get('table_session_started_at');
        if (!$tableId) {
            return false;
        }

        $hasDeliveredOrderSinceSession = Order::where('table_number', (int) $tableId)
            ->where('status', 'DELIVERED')
            ->when($sessionStartedAt, function ($query, $sessionStartedAt) {
                $query->where('updated_at', '>=', $sessionStartedAt);
            })
            ->exists();

        if ($hasDeliveredOrderSinceSession && $this->isTableAvailable((int) $tableId)) {
            $request->session()->forget('table_id');
            $request->session()->forget('table_session_started_at');
            return true;
        }

        return false;
    }

    public function clearTableSession(Request $request): bool
    {
        if (!$request->hasSession()) {
            return false;
        }

        $request->session()->forget('table_id');
        $request->session()->forget('table_session_started_at');
        return true;
    }

    public function storeTableSession(Request $request, int $tableId): void
    {
        $request->session()->put('table_id', $tableId);
        $request->session()->put('table_session_started_at', now()->toDateTimeString());
    }
}
