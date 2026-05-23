<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Domains\Table\Services\TableService;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use App\Models\TableOccupancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class TableController extends Controller
{
    private const ACTIVE_ORDER_STATUSES = ['CONFIRMED', 'IN_QUEUE', 'IN_PROGRESS'];
    private const PAID_STATUSES = ['PAID', 'SUCCESS', 'SETTLEMENT'];
    private const HOLD_ORDER_STATUSES = ['PENDING_PAYMENT'];
    private const HOLD_PAYMENT_STATUSES = ['PENDING'];

    public function __construct(private readonly TableService $tableService)
    {
    }

    public function indexPage()
    {
        $this->tableService->autoClearExpiredDeliveredAssignments();
        $this->tableService->syncTableOccupanciesFromOrders();
        $now = now();
        $timezone = 'Asia/Jakarta';
        $todayStart = $now->copy()->timezone($timezone)->startOfDay();
        $todayEnd = $now->copy()->timezone($timezone)->endOfDay();

        $knownTableIds = collect(config('tables.known_table_ids', []));

        if ($knownTableIds->isEmpty()) {
            $knownTableIds = collect(range(
                (int) config('tables.min_table_id', 1),
                (int) config('tables.max_table_id', 100)
            ));
        }

        $activeOrderIds = $this->tableService->activeOccupancySlotsQuery()
            ->pluck('order_id')
            ->map(fn ($id) => (string) $id)
            ->filter(fn ($id) => $id !== '')
            ->unique()
            ->values()
            ->toArray();

        $occupyingOrders = Order::with('customer')
            ->whereIn('_id', $activeOrderIds)
            ->where('table_number', '>', 0)
            ->orderBy('queue_number', 'asc')
            ->orderBy('_id', 'desc')
            ->get();

        $activeOrders = Order::with('customer')
            ->whereIn('payment_status', self::PAID_STATUSES)
            ->whereIn('status', self::ACTIVE_ORDER_STATUSES)
            ->orderBy('queue_number', 'asc')
            ->orderBy('_id', 'desc')
            ->get();

        $ordersByTable = $occupyingOrders->groupBy(function (Order $order) {
            return (int) $order->table_number;
        });
        $bookingItemsByTable = TableOccupancy::query()
            ->where('order_type', 'booking_dine_in')
            ->whereNotNull('start_at')
            ->where('end_at', '>', $now)
            ->orderBy('start_at', 'asc')
            ->get()
            ->map(function (TableOccupancy $slot) {
                $startAt = $slot->start_at;
                $endAt = $slot->end_at;
                $durationHours = 0;
                if ($startAt && $endAt) {
                    try {
                        $durationHours = max(0, (int) round($endAt->diffInMinutes($startAt) / 60));
                    } catch (\Throwable $exception) {
                        $durationHours = 0;
                    }
                }

                return [
                    'bookingId' => (string) ($slot->order_id ?? ''),
                    'displayId' => (string) ($slot->display_id ?? '-'),
                    'tableNumber' => (int) ($slot->table_number ?? 0),
                    'status' => strtoupper((string) ($slot->status ?? 'UNKNOWN')),
                    'customerName' => (string) ($slot->customer_name ?? '-'),
                    'customerEmail' => (string) ($slot->customer_email ?? '-'),
                    'durationHours' => $durationHours,
                    'bookingStartAt' => optional($startAt)?->toIso8601String(),
                    'bookingEndAt' => optional($endAt)?->toIso8601String(),
                ];
            })
            ->groupBy(function (array $item) {
                return (int) ($item['tableNumber'] ?? 0);
            });

        $tableSnapshots = $knownTableIds->map(function ($tableId) use ($ordersByTable, $bookingItemsByTable, $timezone, $todayStart, $todayEnd, $now) {
            $tableId = (int) $tableId;
            $occupants = $ordersByTable->get($tableId, collect());
            $primary = $occupants->first();
            $occupyingOrderItems = $occupants
                ->map(function (Order $order) {
                    return $this->buildOrderLitePayload($order);
                })
                ->values();
            $bookingItems = collect($bookingItemsByTable->get($tableId, collect()))->values();
            $todaySectionItems = collect();
            $upcomingSectionItems = collect();
            $nowLocal = $now->copy()->setTimezone($timezone);
            $hasRunningBookingNow = false;
            $runningBookingNowItem = null;

            foreach ($bookingItems as $bookingItem) {
                $startAtRaw = (string) ($bookingItem['bookingStartAt'] ?? '');
                if ($startAtRaw === '') {
                    continue;
                }

                try {
                    $startAtLocal = Carbon::parse($startAtRaw)->setTimezone($timezone);
                } catch (\Throwable $exception) {
                    continue;
                }

                $endAtLocal = null;
                $endAtRaw = (string) ($bookingItem['bookingEndAt'] ?? '');
                if ($endAtRaw !== '') {
                    try {
                        $endAtLocal = Carbon::parse($endAtRaw)->setTimezone($timezone);
                    } catch (\Throwable $exception) {
                        $endAtLocal = null;
                    }
                }

                if ($endAtLocal && $endAtLocal->lte($nowLocal)) {
                    continue;
                }

                if ($startAtLocal->lte($nowLocal) && (!$endAtLocal || $endAtLocal->gt($nowLocal))) {
                    $hasRunningBookingNow = true;
                    $runningBookingNowItem ??= $bookingItem;
                }

                if ($startAtLocal->lt($todayStart)) {
                    continue;
                }

                if ($startAtLocal->between($todayStart, $todayEnd)) {
                    $todaySectionItems->push($bookingItem);
                    continue;
                }

                if ($startAtLocal->gt($todayEnd)) {
                    $upcomingSectionItems->push($bookingItem);
                }
            }

            $todaySectionItems = $occupyingOrderItems
                ->reject(function (array $orderItem) {
                    return strtolower((string) ($orderItem['orderType'] ?? '')) === 'booking_dine_in';
                })
                ->map(function (array $orderItem) {
                    return [
                        'entryType' => 'order',
                        'data' => $orderItem,
                    ];
                })
                ->concat(
                    $todaySectionItems->map(function (array $bookingItem) {
                        return [
                            'entryType' => 'booking',
                            'data' => $bookingItem,
                        ];
                    })
                )
                ->values();

            $upcomingSectionItems = $upcomingSectionItems
                ->map(function (array $bookingItem) {
                    return [
                        'entryType' => 'booking',
                        'data' => $bookingItem,
                    ];
                })
                ->values();

            $fallbackCurrentOrder = null;
            if ($primary === null && $runningBookingNowItem !== null) {
                $startTimeLabel = null;
                $endTimeLabel = null;
                $startRaw = (string) ($runningBookingNowItem['bookingStartAt'] ?? '');
                $endRaw = (string) ($runningBookingNowItem['bookingEndAt'] ?? '');
                if ($startRaw !== '') {
                    try {
                        $startTimeLabel = Carbon::parse($startRaw)->setTimezone($timezone)->format('H.i');
                    } catch (\Throwable $exception) {
                        $startTimeLabel = null;
                    }
                }
                if ($endRaw !== '') {
                    try {
                        $endTimeLabel = Carbon::parse($endRaw)->setTimezone($timezone)->format('H.i');
                    } catch (\Throwable $exception) {
                        $endTimeLabel = null;
                    }
                }
                $timeRangeLabel = ($startTimeLabel !== null && $endTimeLabel !== null)
                    ? $startTimeLabel . '-' . $endTimeLabel
                    : null;

                $fallbackCurrentOrder = [
                    'orderId' => (string) ($runningBookingNowItem['bookingId'] ?? ''),
                    'displayId' => (string) ($runningBookingNowItem['displayId'] ?? '-'),
                    'tableNumber' => (int) ($runningBookingNowItem['tableNumber'] ?? $tableId),
                    'status' => (string) ($runningBookingNowItem['status'] ?? 'CONFIRMED'),
                    'queueNumber' => 0,
                    'customerName' => (string) ($runningBookingNowItem['customerName'] ?? '-'),
                    'customerEmail' => (string) ($runningBookingNowItem['customerEmail'] ?? '-'),
                    'bookingTimeRange' => $timeRangeLabel,
                ];
            }

            $activeOrderIds = collect($occupyingOrderItems)
                ->pluck('orderId')
                ->map(fn ($id) => (string) $id)
                ->filter(fn ($id) => $id !== '')
                ->values();
            if ($hasRunningBookingNow && $runningBookingNowItem !== null) {
                $activeOrderIds->push((string) ($runningBookingNowItem['bookingId'] ?? ''));
            }
            $activeStatuses = $occupyingOrderItems
                ->pluck('status')
                ->map(fn ($status) => strtoupper((string) $status))
                ->filter(fn ($status) => $status !== '')
                ->values();
            if ($hasRunningBookingNow && $runningBookingNowItem !== null) {
                $activeStatuses->push(strtoupper((string) ($runningBookingNowItem['status'] ?? 'UNKNOWN')));
            }
            $canClearNow = $activeStatuses->isNotEmpty()
                && $activeStatuses->every(fn ($status) => in_array($status, ['DELIVERED', 'PENDING_PAYMENT'], true));

            return [
                'tableId' => $tableId,
                'isOccupied' => $occupants->isNotEmpty() || $hasRunningBookingNow,
                'activeOrderCount' => $activeOrderIds->unique()->count(),
                'canClearNow' => $canClearNow,
                'currentOrder' => $primary ? $this->buildOrderLitePayload($primary) : $fallbackCurrentOrder,
                'occupyingOrders' => $occupyingOrderItems,
                'todaySectionItems' => $todaySectionItems,
                'upcomingSectionItems' => $upcomingSectionItems,
            ];
        })->values();

        $assignableOrders = $activeOrders
            ->map(function (Order $order) {
                return $this->buildOrderLitePayload($order);
            })
            ->values();

        $availableTables = $tableSnapshots
            ->filter(fn (array $table) => empty($table['isOccupied']))
            ->values();

        return view('backoffice.table.index', [
            'tables' => $tableSnapshots,
            'assignableOrders' => $assignableOrders,
            'availableTables' => $availableTables,
            'tableStats' => [
                'total' => $tableSnapshots->count(),
                'occupied' => $tableSnapshots->where('isOccupied', true)->count(),
                'available' => $tableSnapshots->where('isOccupied', false)->count(),
                'activeOrders' => $assignableOrders->count(),
            ],
        ]);
    }

    public function assignPage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string',
            'table_number' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $targetTable = (int) $request->input('table_number');
        if (! $this->tableService->isKnownTable($targetTable)) {
            return redirect()->back()->with('error', 'Nomor meja tidak terdaftar.')->withInput();
        }

        $order = Order::find((string) $request->input('order_id'));
        if (! $order) {
            return redirect()->back()->with('error', 'Order tidak ditemukan.')->withInput();
        }

        if (! in_array((string) $order->status, self::ACTIVE_ORDER_STATUSES, true)) {
            return redirect()->back()->with('error', 'Hanya order aktif yang bisa dipindahkan meja.')->withInput();
        }

        if (! in_array(strtoupper((string) ($order->payment_status ?? '')), self::PAID_STATUSES, true)) {
            return redirect()->back()->with('error', 'Hanya order dengan pembayaran lunas yang bisa dipindahkan meja.')->withInput();
        }

        $sourceTable = (int) ($order->table_number ?? 0);

        if ($sourceTable !== $targetTable) {
            $this->tableService->syncTableOccupanciesFromOrders();
            $targetHasActiveOrders = $this->tableService->activeOccupancySlotsQuery($targetTable)
                ->where('order_id', '!=', (string) $order->_id)
                ->exists();

            if ($targetHasActiveOrders) {
                return redirect()->back()->with('error', 'Meja ' . $targetTable . ' sedang penuh. Pilih meja lain.')->withInput();
            }
        }

        $order->update([
            'table_number' => $targetTable,
        ]);
        $this->tableService->syncTableOccupanciesFromOrders();

        return redirect('/backoffice/kelola_meja')->with(
            'success',
            'Order ' . $this->displayOrderId($order) . ' berhasil dipindahkan dari meja ' . $sourceTable . ' ke meja ' . $targetTable . '.'
        );
    }

    public function clearPage(int $tableId)
    {
        if (! $this->tableService->isKnownTable($tableId)) {
            return redirect('/backoffice/kelola_meja')->with('error', 'Nomor meja tidak terdaftar.');
        }

        $this->tableService->syncTableOccupanciesFromOrders();
        $activeOrderIds = $this->tableService->activeOccupancySlotsQuery($tableId)
            ->pluck('order_id')
            ->map(fn ($id) => (string) $id)
            ->filter(fn ($id) => $id !== '')
            ->unique()
            ->values()
            ->toArray();

        $occupyingOrders = Order::whereIn('_id', $activeOrderIds)->get();

        if ($occupyingOrders->isEmpty()) {
            return redirect('/backoffice/kelola_meja')->with('success', 'Meja ' . $tableId . ' sudah dalam kondisi kosong.');
        }

        $hasUndelivered = $occupyingOrders->contains(function (Order $order) {
            $status = strtoupper((string) ($order->status ?? 'UNKNOWN'));
            return ! in_array($status, ['DELIVERED', 'PENDING_PAYMENT'], true);
        });
        if ($hasUndelivered) {
            return redirect('/backoffice/kelola_meja')->with(
                'error',
                'Meja hanya bisa dikosongkan jika semua order aktif berstatus Disajikan atau Menunggu Pembayaran.'
            );
        }

        foreach ($occupyingOrders as $order) {
            $payload = [
                'table_cleared_at' => now(),
            ];

            $order->update($payload);
        }
        $this->tableService->syncTableOccupanciesFromOrders();

        return redirect('/backoffice/kelola_meja')->with(
            'success',
            'Meja ' . $tableId . ' berhasil dikosongkan dan ' . $occupyingOrders->count() . ' order terkait sudah dilepas dari meja.'
        );
    }

    private function buildOrderLitePayload(Order $order): array
    {
        $customer = $order->customer;

        $customerName = $customer?->name
            ?? $order->customer_name
            ?? $customer?->username
            ?? '-';

        $customerEmail = $customer?->email
            ?? $order->customer_email
            ?? $customer?->username
            ?? '-';

        return [
            'orderId' => (string) $order->_id,
            'displayId' => $this->displayOrderId($order),
            'orderType' => strtolower((string) ($order->order_type ?? 'dine_in')),
            'tableNumber' => (int) ($order->table_number ?? 0),
            'status' => (string) ($order->status ?? 'UNKNOWN'),
            'queueNumber' => (int) ($order->queue_number ?? 0),
            'customerName' => $customerName,
            'customerEmail' => $customerEmail,
        ];
    }

    private function displayOrderId(Order $order): string
    {
        return 'ORD-' . strtoupper(substr((string) $order->_id, -6));
    }

    private function buildBookingLitePayload(Booking $booking): array
    {
        $customer = $booking->customer;
        $customerName = $customer?->name ?? $customer?->username ?? '-';
        $customerEmail = $customer?->email ?? $customer?->username ?? '-';

        return [
            'bookingId' => (string) $booking->_id,
            'displayId' => 'BKG-' . strtoupper(substr((string) $booking->_id, -6)),
            'tableNumber' => (int) ($booking->table_number ?? 0),
            'status' => strtoupper((string) ($booking->status ?? 'UNKNOWN')),
            'customerName' => $customerName,
            'customerEmail' => $customerEmail,
            'durationHours' => (int) ($booking->duration_hours ?? 0),
            'bookingStartAt' => optional($booking->booking_start_at)?->toIso8601String(),
            'bookingEndAt' => optional($booking->booking_end_at)?->toIso8601String(),
        ];
    }

    private function buildBookingLitePayloadFromOrder(Order $order): array
    {
        $customer = $order->customer;
        $customerName = $customer?->name ?? $customer?->username ?? '-';
        $customerEmail = $customer?->email ?? $customer?->username ?? '-';

        $mappedStatus = match (strtoupper((string) ($order->status ?? ''))) {
            'PENDING_PAYMENT' => 'PENDING',
            'CONFIRMED' => 'CONFIRMED',
            'IN_QUEUE', 'IN_PROGRESS' => 'SEATED',
            'DELIVERED' => 'COMPLETED',
            default => strtoupper((string) ($order->status ?? 'UNKNOWN')),
        };

        $bookingStartAt = $order->booking_start_at;
        $durationHours = (int) ($order->duration_hours ?? 0);
        $bookingEndAt = null;

        if ($bookingStartAt && $durationHours > 0) {
            try {
                $bookingEndAt = Carbon::parse($bookingStartAt)->addHours($durationHours)->toIso8601String();
            } catch (\Throwable $exception) {
                $bookingEndAt = null;
            }
        }

        return [
            'bookingId' => (string) $order->_id,
            'displayId' => 'ORD-' . strtoupper(substr((string) $order->_id, -6)),
            'tableNumber' => (int) ($order->table_number ?? 0),
            'status' => $mappedStatus,
            'customerName' => $customerName,
            'customerEmail' => $customerEmail,
            'durationHours' => $durationHours,
            'bookingStartAt' => $bookingStartAt ? (is_string($bookingStartAt) ? $bookingStartAt : $bookingStartAt->toIso8601String()) : null,
            'bookingEndAt' => $bookingEndAt,
        ];
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
}
