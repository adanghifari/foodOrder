<?php

namespace App\Domains\Table\Services;

use Carbon\CarbonInterface;
use App\Models\Booking;
use App\Models\Order;
use App\Models\TableOccupancy;
use App\Support\TableGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TableService
{
    private const ACTIVE_ORDER_STATUSES = ['CONFIRMED', 'IN_QUEUE', 'IN_PROGRESS'];
    private const PAID_STATUSES = ['PAID', 'SUCCESS', 'SETTLEMENT'];
    private const HOLD_PAYMENT_STATUSES = ['PENDING'];
    private const HOLD_ORDER_STATUSES = ['PENDING_PAYMENT'];
    private const PENDING_FALLBACK_HOURS = 4;
    private const ACTIVE_OCCUPANCY_ORDER_STATUSES = ['CONFIRMED', 'IN_QUEUE', 'IN_PROGRESS', 'DELIVERED'];
    private const ACTIVE_BOOKING_ORDER_STATUSES = ['PENDING_PAYMENT', 'CONFIRMED', 'IN_QUEUE', 'IN_PROGRESS'];

    public function isKnownTable(int $tableId): bool
    {
        return TableGuard::isKnownTable($tableId);
    }

    public function isTableAvailable(int $tableId): bool
    {
        $this->syncTableOccupanciesFromOrders();

        // For on-the-spot dine-in, availability only depends on current occupancy.
        // Upcoming booking pre-block must not prevent immediate usage.
        return ! $this->activeOccupancySlotsQuery($tableId)->exists();
    }

    public function getTableUnavailableReason(int $tableId): ?string
    {
        $this->syncTableOccupanciesFromOrders();

        if ($this->activeOccupancySlotsQuery($tableId)->exists()) {
            return 'Meja sedang dipakai.';
        }

        return null;
    }

    public function occupyingTableIds(?array $knownTableIds = null): array
    {
        $this->syncTableOccupanciesFromOrders();

        $query = TableOccupancy::query();

        if (is_array($knownTableIds) && !empty($knownTableIds)) {
            $query->whereIn('table_number', $knownTableIds);
        }

        return $this->activeOccupancyWindowConstraint($query, now())
            ->pluck('table_number')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->toArray();
    }

    public function blockingTableIdsForBookingSlot(
        Carbon $slotStartAt,
        Carbon $slotEndAt,
        array $knownTableIds
    ): array {
        if (empty($knownTableIds)) {
            return [];
        }
        $this->syncTableOccupanciesFromOrders();

        return TableOccupancy::query()
            ->whereIn('table_number', $knownTableIds)
            ->where('order_type', '!=', 'booking_dine_in')
            // strict no overlap and no touching boundary:
            // existing.start <= requested.end && existing.end >= requested.start
            ->where('start_at', '<=', $slotEndAt)
            ->where(function ($query) use ($slotStartAt) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', $slotStartAt);
            })
            ->pluck('table_number')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->toArray();
    }

    public function canPlaceOrderForSession(
        int $tableId,
        ?string $customerName = null,
        ?string $customerEmail = null,
        ?string $browserSessionId = null,
        ?int $sessionTableId = null,
        ?int $receiptTableId = null
    ): bool {
        if ($this->isTableAvailable($tableId)) {
            return true;
        }

        $normalizedCustomerName = $this->normalizeCustomerName($customerName);
        $normalizedCustomerEmail = strtolower(trim((string) $customerEmail));
        $normalizedBrowserSessionId = trim((string) $browserSessionId);

        $occupyingOrders = $this->occupyingOrdersQuery($tableId)
            ->with('customer')
            ->get([
                'customer_id',
                'customer_name',
                'customer_email',
                'browser_session_id',
            ]);

        return $occupyingOrders->contains(function ($order) use ($normalizedCustomerName, $normalizedCustomerEmail, $normalizedBrowserSessionId) {
            if (!$order instanceof Order) {
                return false;
            }

            $orderBrowserSessionId = trim((string) ($order->browser_session_id ?? ''));
            if ($normalizedBrowserSessionId !== '' && $orderBrowserSessionId === $normalizedBrowserSessionId) {
                return true;
            }

            if ($normalizedCustomerName === '') {
                return false;
            }

            $occupantName = collect([
                (string) ($order->customer_name ?? ''),
                (string) ($order->customer?->name ?? ''),
                (string) ($order->customer?->username ?? ''),
            ])->map(fn ($value) => trim($value))
                ->first(fn ($value) => $value !== '');

            if ($this->normalizeCustomerName((string) $occupantName) === $normalizedCustomerName) {
                return true;
            }

            $resolvedCustomerEmail = collect([
                (string) ($order->customer_email ?? ''),
                (string) ($order->customer?->email ?? ''),
                (string) ($order->customer?->username ?? ''),
            ])->map(fn ($value) => strtolower(trim($value)))
                ->first(fn ($value) => $value !== '');

            return $normalizedCustomerEmail !== '' && $normalizedCustomerEmail === (string) $resolvedCustomerEmail;
        });
    }

    public function occupyingOrdersQuery(int $tableId)
    {
        $this->syncTableOccupanciesFromOrders();

        $activeOrderIds = $this->activeOccupancySlotsQuery($tableId)
            ->pluck('order_id')
            ->filter(fn ($id) => trim((string) $id) !== '')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->toArray();

        return Order::whereIn('_id', $activeOrderIds);
    }

    private function applyBookingDineInStartConstraint(Builder $query): Builder
    {
        $now = now();

        return $query->where(function ($scopedQuery) use ($now) {
            $scopedQuery->where('order_type', '!=', 'booking_dine_in')
                ->orWhere(function ($bookingDineInQuery) use ($now) {
                    $bookingDineInQuery->where('order_type', 'booking_dine_in')
                        ->where('booking_start_at', '<=', $now);
                });
        });
    }

    private function isPreBlockedByUpcomingBooking(int $tableId): bool
    {
        $preBlockHours = max(0, (int) config('booking.pre_block_hours', 2));
        $now = now();
        $preBlockBoundary = $now->copy()->addHours($preBlockHours);

        return Booking::where('table_number', $tableId)
            ->whereIn('status', ['PENDING', 'CONFIRMED', 'SEATED'])
            ->where('booking_end_at', '>', $now)
            ->where('booking_start_at', '<=', $preBlockBoundary)
            ->exists();
    }

    public function autoClearExpiredDeliveredAssignments(?int $graceMinutes = null): int
    {
        return 0;
    }

    public function syncTableOccupanciesFromOrders(): void
    {
        $orders = Order::with('customer')
            ->where('table_number', '>', 0)
            ->whereNull('order_deleted_at')
            ->where(function ($query) {
                $query->where(function ($paidFlowQuery) {
                    $paidFlowQuery->whereIn('payment_status', self::PAID_STATUSES)
                        ->whereIn('status', self::ACTIVE_OCCUPANCY_ORDER_STATUSES)
                        ->whereNull('table_cleared_at');
                })->orWhere(function ($pendingHoldQuery) {
                    $pendingHoldQuery->whereIn('payment_status', self::HOLD_PAYMENT_STATUSES)
                        ->whereIn('status', self::HOLD_ORDER_STATUSES)
                        ->whereNull('table_cleared_at');
                });
            })
            ->get();

        $syncedOrderIds = [];
        foreach ($orders as $order) {
            if (! $order instanceof Order) {
                continue;
            }

            $interval = $this->resolveOrderOccupancyInterval($order);
            if ($interval === null) {
                continue;
            }

            $customerName = (string) ($order->customer?->name
                ?? $order->customer_name
                ?? $order->customer?->username
                ?? '-');
            $customerEmail = (string) ($order->customer?->email
                ?? $order->customer_email
                ?? $order->customer?->username
                ?? '-');

            $orderId = (string) $order->_id;
            $syncedOrderIds[] = $orderId;

            TableOccupancy::updateOrCreate(
                ['order_id' => $orderId],
                [
                    'table_number' => (int) ($order->table_number ?? 0),
                    'order_type' => strtolower((string) ($order->order_type ?? 'dine_in')),
                    'source_type' => strtolower((string) ($order->order_type ?? '')) === 'booking_dine_in' ? 'booking' : 'order',
                    'status' => strtoupper((string) ($order->status ?? 'UNKNOWN')),
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'display_id' => 'ORD-' . strtoupper(substr($orderId, -6)),
                    'start_at' => $interval['start_at'],
                    'end_at' => $interval['end_at'],
                    'meta' => [
                        'duration_hours' => (int) ($order->duration_hours ?? 0),
                        'queue_number' => (int) ($order->queue_number ?? 0),
                    ],
                ]
            );
        }

        if (empty($syncedOrderIds)) {
            TableOccupancy::query()->delete();
            return;
        }

        TableOccupancy::query()
            ->whereNotIn('order_id', $syncedOrderIds)
            ->delete();
    }

    public function activeOccupancySlotsQuery(?int $tableId = null, ?Carbon $at = null)
    {
        $query = TableOccupancy::query();
        if ($tableId !== null) {
            $query->where('table_number', (int) $tableId);
        }

        return $this->activeOccupancyWindowConstraint($query, $at ?? now());
    }

    private function activeOccupancyWindowConstraint(Builder $query, CarbonInterface $at): Builder
    {
        return $query
            ->where('start_at', '<=', $at)
            ->where(function ($windowQuery) use ($at) {
                $windowQuery->whereNull('end_at')
                    ->orWhere('end_at', '>', $at);
            });
    }

    private function resolveOrderOccupancyInterval(Order $order): ?array
    {
        $orderType = strtolower((string) ($order->order_type ?? ''));
        $startAt = null;
        $endAt = null;

        if ($orderType === 'booking_dine_in') {
            if (empty($order->booking_start_at) || ((int) ($order->duration_hours ?? 0)) <= 0) {
                return null;
            }

            try {
                $startAt = Carbon::parse($order->booking_start_at);
                $endAt = $startAt->copy()->addHours((int) $order->duration_hours);
            } catch (\Throwable $exception) {
                return null;
            }

            if (!empty($order->table_cleared_at)) {
                try {
                    $clearedAt = Carbon::parse($order->table_cleared_at);
                    if ($clearedAt->lt($endAt)) {
                        $endAt = $clearedAt;
                    }
                } catch (\Throwable $exception) {
                    // keep original schedule end
                }
            }
        } else {
            try {
                $startAt = $order->paid_at
                    ? Carbon::parse($order->paid_at)
                    : Carbon::parse($order->created_at);
            } catch (\Throwable $exception) {
                return null;
            }

            if (!empty($order->table_cleared_at)) {
                try {
                    $endAt = Carbon::parse($order->table_cleared_at);
                } catch (\Throwable $exception) {
                    $endAt = null;
                }
            } else {
                // Fallback hold window for on-the-spot dine-in from pending phase.
                $endAt = $startAt->copy()->addHours(self::PENDING_FALLBACK_HOURS);
            }
        }

        if ($endAt !== null && $endAt->lte($startAt)) {
            return null;
        }

        return [
            'start_at' => $startAt,
            'end_at' => $endAt,
        ];
    }

    public function getOnSpotBookingAdvisory(int $tableId): array
    {
        $timezone = 'Asia/Jakarta';
        $now = now()->setTimezone($timezone);
        $bufferHours = max(0, (int) config('booking.pre_block_hours', 2));

        $nextBookingOrder = Order::query()
            ->where('table_number', $tableId)
            ->where('order_type', 'booking_dine_in')
            ->whereNull('order_deleted_at')
            ->whereIn('status', self::ACTIVE_BOOKING_ORDER_STATUSES)
            ->whereNotNull('booking_start_at')
            ->orderBy('booking_start_at', 'asc')
            ->first();

        if (!$nextBookingOrder instanceof Order || empty($nextBookingOrder->booking_start_at)) {
            return [
                'level' => 'none',
                'hasAdvisory' => false,
            ];
        }

        try {
            $bookingStartAt = Carbon::parse($nextBookingOrder->booking_start_at)->setTimezone($timezone);
        } catch (\Throwable $exception) {
            return [
                'level' => 'none',
                'hasAdvisory' => false,
            ];
        }

        $blockedStartAt = $bookingStartAt->copy()->subHours($bufferHours);
        $minutesUntilBlocked = (int) $now->diffInMinutes($blockedStartAt, false);
        if ($minutesUntilBlocked > 180) {
            return [
                'level' => 'none',
                'hasAdvisory' => false,
            ];
        }

        $minimumRequiredMinutes = 120;
        $availableMinutes = max(0, $minutesUntilBlocked);
        $availableHours = intdiv($availableMinutes, 60);
        $availableRemainderMinutes = $availableMinutes % 60;
        $durationLabel = trim(($availableHours > 0 ? $availableHours . ' jam ' : '') . $availableRemainderMinutes . ' menit');
        $level = $minutesUntilBlocked < $minimumRequiredMinutes ? 'blocked' : 'warning';
        $message = $level === 'blocked'
            ? 'Mohon maaf, di meja ini sudah ada yang booking di waktu '
                . $bookingStartAt->format('H.i')
                . '. Pemesanan meja hanya bisa sampai maksimal 2 jam sebelum area reservasi dimulai, silahkan pilih meja kosong lainnya.'
            : 'Mohon maaf, di meja ini sudah ada yang booking di waktu '
                . $bookingStartAt->format('H.i')
                . ', jadi anda bisa menempati meja ini dengan durasi '
                . $durationLabel
                . '. Jika ingin waktu yang fleksibel silahkan pilih meja kosong lainnya.';

        return [
            'level' => $level,
            'hasAdvisory' => true,
            'tableNumber' => $tableId,
            'nextBookingStartAt' => $bookingStartAt->toIso8601String(),
            'blockedStartAt' => $blockedStartAt->toIso8601String(),
            'minutesUntilBlocked' => $minutesUntilBlocked,
            'availableMinutes' => $availableMinutes,
            'availableDurationLabel' => $durationLabel,
            'message' => $message,
        ];
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

        $hasAnyOrderSinceSession = false;
        if ($sessionStartedAt) {
            try {
                $sessionStart = Carbon::parse($sessionStartedAt);
                $hasAnyOrderSinceSession = Order::where('table_number', (int) $tableId)
                    ->where('created_at', '>=', $sessionStart)
                    ->exists();
            } catch (\Throwable) {
                $hasAnyOrderSinceSession = false;
            }
        }

        // If user scanned a table but did not place any order within 1 hour,
        // expire the table session automatically.
        if ($sessionStartedAt) {
            $sessionStart = Carbon::parse($sessionStartedAt);

            if (!$hasAnyOrderSinceSession && now()->gte($sessionStart->copy()->addHour())) {
                $this->clearSessionKeys($request, false);
                return true;
            }
        }

        // Important:
        // Do not clear immediately after first scan when table is still available
        // and user has not placed any order yet.
        // Only clear available table sessions when there has been at least one
        // order in this session window (e.g. order finished / table released).
        if ($hasAnyOrderSinceSession && $this->isTableAvailable((int) $tableId)) {
            $this->clearSessionKeys($request, false);
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
        $request->session()->put('order_type', 'DINE_IN');
        $request->session()->put('table_session_started_at', now()->toDateTimeString());
    }

    public function storeTakeAwaySession(Request $request): void
    {
        $request->session()->forget('table_id');
        $request->session()->put('order_type', 'TAKE_AWAY');
        $request->session()->put('table_session_started_at', now()->toDateTimeString());
    }

    public function normalizeCustomerName(?string $name): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $name));

        return mb_strtolower((string) ($normalized ?? ''));
    }

    public function normalizeCustomerEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    private function clearSessionKeys(Request $request, bool $clearReceiptContext = true): void
    {
        $request->session()->forget('table_id');
        $request->session()->forget('order_type');
        $request->session()->forget('table_session_started_at');
        if ($clearReceiptContext) {
            $request->session()->forget('frontliner_receipt_order_id');
            $request->session()->forget('frontliner_receipt_order_ids');
            $request->session()->forget('frontliner_receipt_table_id');
            $request->session()->forget('frontliner_receipt_bound_at');
        }
    }
}
