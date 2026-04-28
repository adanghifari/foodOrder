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
    private const HOLD_PAYMENT_STATUSES = ['PENDING'];
    private const HOLD_ORDER_STATUSES = ['PENDING_PAYMENT'];
    private const DELIVERED_GRACE_MINUTES = 150;

    public function isKnownTable(int $tableId): bool
    {
        return TableGuard::isKnownTable($tableId);
    }

    public function isTableAvailable(int $tableId): bool
    {
        return ! $this->occupyingOrdersQuery($tableId)->exists();
    }

    public function canPlaceOrderForSession(
        int $tableId,
        ?string $customerName = null,
        ?string $browserSessionId = null,
        ?int $sessionTableId = null,
        ?int $receiptTableId = null
    ): bool {
        if ($this->isTableAvailable($tableId)) {
            return true;
        }

        $normalizedCustomerName = $this->normalizeCustomerName($customerName);
        $normalizedBrowserSessionId = trim((string) $browserSessionId);

        $occupyingOrders = $this->occupyingOrdersQuery($tableId)->get([
            'customer_name',
            'browser_session_id',
        ]);

        return $occupyingOrders->contains(function (Order $order) use ($normalizedCustomerName, $normalizedBrowserSessionId) {
            $orderBrowserSessionId = trim((string) ($order->browser_session_id ?? ''));
            if ($normalizedBrowserSessionId !== '' && $orderBrowserSessionId === $normalizedBrowserSessionId) {
                return true;
            }

            return $normalizedCustomerName !== ''
                && $this->normalizeCustomerName((string) ($order->customer_name ?? '')) === $normalizedCustomerName;
        });
    }

    public function occupyingOrdersQuery(int $tableId)
    {
        return Order::where('table_number', $tableId)
            ->where(function ($query) {
                $query->where(function ($paidFlowQuery) {
                    $paidFlowQuery->whereIn('payment_status', self::PAID_STATUSES)
                        ->where(function ($paidStatusQuery) {
                            $paidStatusQuery->whereIn('status', self::ACTIVE_ORDER_STATUSES)
                                ->orWhere(function ($deliveredQuery) {
                                    $deliveredQuery->where('status', 'DELIVERED')
                                        ->whereNull('table_cleared_at');
                                });
                        });
                })->orWhere(function ($pendingFlowQuery) {
                    // Keep table reserved as soon as payment is initiated.
                    $pendingFlowQuery->whereIn('payment_status', self::HOLD_PAYMENT_STATUSES)
                        ->whereIn('status', self::HOLD_ORDER_STATUSES)
                        ->whereNull('table_cleared_at');
                });
            });
    }

    public function autoClearExpiredDeliveredAssignments(?int $graceMinutes = null): int
    {
        $effectiveGraceMinutes = max(1, (int) ($graceMinutes ?? self::DELIVERED_GRACE_MINUTES));
        $cutoff = now()->subMinutes($effectiveGraceMinutes);

        $expiredDeliveredOrders = Order::where('status', 'DELIVERED')
            ->whereIn('payment_status', self::PAID_STATUSES)
            ->whereNull('table_cleared_at')
            ->where(function ($query) use ($cutoff) {
                $query->where('delivered_at', '<=', $cutoff)
                    ->orWhere(function ($fallbackQuery) use ($cutoff) {
                        $fallbackQuery->whereNull('delivered_at')
                            ->where('updated_at', '<=', $cutoff);
                    });
            })
            ->get();

        if ($expiredDeliveredOrders->isEmpty()) {
            return 0;
        }

        $now = now();
        foreach ($expiredDeliveredOrders as $order) {
            $order->update([
                'table_cleared_at' => $order->table_cleared_at ?? $now,
            ]);
        }

        return $expiredDeliveredOrders->count();
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

        if (!$deliveredAt || now()->lt($deliveredAt->copy()->addMinutes(self::DELIVERED_GRACE_MINUTES))) {
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
            // Keep endpoint idempotent for stateless API clients.
            return true;
        }

        $this->clearSessionKeys($request);
        return true;
    }

    public function storeTableSession(Request $request, int $tableId): void
    {
        $request->session()->put('table_id', $tableId);
        $request->session()->put('table_session_started_at', now()->toDateTimeString());
    }

    public function normalizeCustomerName(?string $name): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $name));

        return mb_strtolower((string) ($normalized ?? ''));
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
