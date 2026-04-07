<?php

namespace App\Domains\Table\Services;

use App\Models\Order;
use App\Support\TableGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TableService
{
    private const ACTIVE_ORDER_STATUSES = ['CONFIRMED', 'IN_QUEUE', 'IN_PROGRESS'];
    private const PAID_STATUSES = ['PAID', 'SUCCESS', 'SETTLEMENT'];

    public function isKnownTable(int $tableId): bool
    {
        return TableGuard::isKnownTable($tableId);
    }

    public function isTableAvailable(int $tableId): bool
    {
        return ! $this->occupyingOrdersQuery($tableId)->exists();
    }

    public function occupyingOrdersQuery(int $tableId)
    {
        return Order::where('table_number', $tableId)
            ->whereIn('payment_status', self::PAID_STATUSES)
            ->where(function ($query) {
                $query->whereIn('status', self::ACTIVE_ORDER_STATUSES)
                    ->orWhere(function ($deliveredQuery) {
                        $deliveredQuery->where('status', 'DELIVERED')
                            ->whereNull('table_cleared_at');
                    });
            });
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

        // If user scanned a table but did not place any order within 1 hour,
        // expire the table session automatically.
        if ($sessionStartedAt) {
            $sessionStart = Carbon::parse($sessionStartedAt);

            $hasAnyOrderSinceSession = Order::where('table_number', (int) $tableId)
                ->where('created_at', '>=', $sessionStart)
                ->exists();

            if (!$hasAnyOrderSinceSession && now()->gte($sessionStart->copy()->addHour())) {
                $this->clearSessionKeys($request);
                return true;
            }
        }

        $latestDeliveredOrderSinceSession = Order::where('table_number', (int) $tableId)
            ->where('status', 'DELIVERED')
            ->when($sessionStartedAt, function ($query, $sessionStartedAt) {
                $query->where('updated_at', '>=', $sessionStartedAt);
            })
            ->orderBy('delivered_at', 'desc')
            ->orderBy('updated_at', 'desc')
            ->first();

        if (!$latestDeliveredOrderSinceSession) {
            return false;
        }

        $deliveredAt = $latestDeliveredOrderSinceSession->delivered_at
            ?? $latestDeliveredOrderSinceSession->updated_at;

        if (!$deliveredAt || now()->lt($deliveredAt->copy()->addHours(2))) {
            return false;
        }

        if ($this->isTableAvailable((int) $tableId)) {
            $this->clearSessionKeys($request);
            return true;
        }

        return false;
    }

    public function clearTableSession(Request $request): bool
    {
        if (!$request->hasSession()) {
            return false;
        }

        $this->clearSessionKeys($request);
        return true;
    }

    public function storeTableSession(Request $request, int $tableId): void
    {
        $request->session()->put('table_id', $tableId);
        $request->session()->put('table_session_started_at', now()->toDateTimeString());
    }

    private function clearSessionKeys(Request $request): void
    {
        $request->session()->forget('table_id');
        $request->session()->forget('table_session_started_at');
        $request->session()->forget('frontliner_receipt_order_id');
        $request->session()->forget('frontliner_receipt_order_ids');
        $request->session()->forget('frontliner_receipt_table_id');
        $request->session()->forget('frontliner_receipt_bound_at');
    }
}
