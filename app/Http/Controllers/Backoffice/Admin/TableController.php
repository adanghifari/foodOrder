<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Domains\Table\Services\TableService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
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

        $knownTableIds = collect(config('tables.known_table_ids', []));

        if ($knownTableIds->isEmpty()) {
            $knownTableIds = collect(range(
                (int) config('tables.min_table_id', 1),
                (int) config('tables.max_table_id', 100)
            ));
        }

        $occupyingOrders = Order::with('customer')
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

        $tableSnapshots = $knownTableIds->map(function ($tableId) use ($ordersByTable) {
            $tableId = (int) $tableId;
            $occupants = $ordersByTable->get($tableId, collect());
            $primary = $occupants->first();

            return [
                'tableId' => $tableId,
                'isOccupied' => $occupants->isNotEmpty(),
                'activeOrderCount' => $occupants->count(),
                'currentOrder' => $primary ? $this->buildOrderLitePayload($primary) : null,
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
            $targetHasActiveOrders = Order::where('table_number', $targetTable)
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

        $occupyingOrders = Order::where('table_number', $tableId)
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
}
