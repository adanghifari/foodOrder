<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Domains\Table\Services\TableService;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
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

        $occupyingOrders = $this->applyBookingDineInStartConstraint(
            Order::with('customer')
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
                    $pendingFlowQuery->whereIn('payment_status', self::HOLD_PAYMENT_STATUSES)
                        ->whereIn('status', self::HOLD_ORDER_STATUSES)
                        ->whereNull('table_cleared_at');
                });
            })
        )
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
        $activeBookingStatuses = ['PENDING', 'CONFIRMED', 'SEATED'];
        $bookingRecords = Booking::with('customer')
            ->whereIn('status', $activeBookingStatuses)
            ->where('booking_end_at', '>', $now)
            ->orderBy('booking_start_at', 'asc')
            ->get();
        $bookingOrderStatuses = ['PENDING_PAYMENT', 'CONFIRMED', 'IN_QUEUE', 'IN_PROGRESS'];
        $bookingDineInOrders = Order::with('customer')
            ->where('order_type', 'booking_dine_in')
            ->whereNull('order_deleted_at')
            ->whereIn('status', $bookingOrderStatuses)
            ->orderBy('booking_start_at', 'asc')
            ->get();
        $bookingItemsByTable = $bookingRecords
            ->map(function (Booking $booking) {
                return $this->buildBookingLitePayload($booking);
            })
            ->concat(
                $bookingDineInOrders->map(function (Order $order) {
                    return $this->buildBookingLitePayloadFromOrder($order);
                })
            )
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

            return [
                'tableId' => $tableId,
                'isOccupied' => $occupants->isNotEmpty(),
                'activeOrderCount' => $occupants->count(),
                'currentOrder' => $primary ? $this->buildOrderLitePayload($primary) : null,
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
            $targetHasActiveOrders = $this->applyBookingDineInStartConstraint(
                Order::where('table_number', $targetTable)
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
                        $pendingFlowQuery->whereIn('payment_status', self::HOLD_PAYMENT_STATUSES)
                            ->whereIn('status', self::HOLD_ORDER_STATUSES)
                            ->whereNull('table_cleared_at');
                    });
                })
                ->where('_id', '!=', $order->_id)
            )
                ->exists();

            if ($targetHasActiveOrders) {
                return redirect()->back()->with('error', 'Meja ' . $targetTable . ' sedang penuh. Pilih meja lain.')->withInput();
            }
        }

        $order->update([
            'table_number' => $targetTable,
        ]);

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

        $occupyingOrders = $this->applyBookingDineInStartConstraint(
            Order::where('table_number', $tableId)
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
                    $pendingFlowQuery->whereIn('payment_status', self::HOLD_PAYMENT_STATUSES)
                        ->whereIn('status', self::HOLD_ORDER_STATUSES)
                        ->whereNull('table_cleared_at');
                });
            })
        )
            ->get();

        if ($occupyingOrders->isEmpty()) {
            return redirect('/backoffice/kelola_meja')->with('success', 'Meja ' . $tableId . ' sudah dalam kondisi kosong.');
        }

        foreach ($occupyingOrders as $order) {
            $payload = [
                'table_cleared_at' => now(),
            ];

            if (in_array((string) $order->status, self::ACTIVE_ORDER_STATUSES, true)) {
                $payload['status'] = 'DELIVERED';
                $payload['delivered_at'] = now();
            }

            $order->update($payload);
        }

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
