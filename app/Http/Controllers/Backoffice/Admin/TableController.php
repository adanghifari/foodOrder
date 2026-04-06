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

    public function __construct(private readonly TableService $tableService)
    {
    }

    public function indexPage()
    {
        $knownTableIds = collect(config('tables.known_table_ids', []));

        if ($knownTableIds->isEmpty()) {
            $knownTableIds = collect(range(
                (int) config('tables.min_table_id', 1),
                (int) config('tables.max_table_id', 100)
            ));
        }

        $activeOrders = Order::with('customer')
            ->whereIn('status', self::ACTIVE_ORDER_STATUSES)
            ->orderBy('queue_number', 'asc')
            ->orderBy('_id', 'desc')
            ->get();

        $ordersByTable = $activeOrders->groupBy(function (Order $order) {
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

        return view('backoffice.table.index', [
            'tables' => $tableSnapshots,
            'assignableOrders' => $assignableOrders,
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

        $sourceTable = (int) ($order->table_number ?? 0);

        if ($sourceTable !== $targetTable) {
            $targetHasActiveOrders = Order::where('table_number', $targetTable)
                ->whereIn('status', self::ACTIVE_ORDER_STATUSES)
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
