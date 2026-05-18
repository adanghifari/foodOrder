<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;

class DashboardController extends Controller
{
    private const PAID_STATUSES = ['PAID', 'SUCCESS', 'SETTLEMENT'];

    public function index()
    {
        $orders = Order::orderBy('_id', 'desc')
            ->whereNull('order_deleted_at')
            ->where('status', 'CONFIRMED')
            ->whereIn('payment_status', self::PAID_STATUSES)
            ->limit(12)
            ->get();

        $activeOrders = $orders->map(function (Order $order) {
            $itemRows = collect($this->normalizeOrderItems($order->items))
                ->groupBy('name')
                ->map(function ($rows, $name) {
                    return [
                        'name' => (string) $name,
                        'quantity' => $rows->count(),
                    ];
                })
                ->values()
                ->toArray();

            $customerName = trim((string) ($order->customer_name ?? ''));

            return [
                'id' => (string) $order->_id,
                'display_id' => 'ORD-' . strtoupper(substr((string) $order->_id, -6)),
                'status' => (string) ($order->status ?? 'UNKNOWN'),
                'order_type' => strtoupper((string) ($order->order_type ?? 'DINE_IN')),
                'customer_name' => $customerName !== '' ? $customerName : '-',
                'customer_email' => trim((string) ($order->customer_email ?? '')) ?: '-',
                'queue_number' => (int) ($order->queue_number ?? 0),
                'table_number' => (int) ($order->table_number ?? 0),
                'total_price' => (float) ($order->total_price ?? 0),
                'item_count' => $this->countOrderItems($this->normalizeOrderItems($order->items)),
                'items' => $itemRows,
            ];
        });

        $detailOrderId = (string) request()->query('detail', '');
        $selectedOrder = null;
        if ($detailOrderId !== '') {
            $selectedOrder = $activeOrders->firstWhere('id', $detailOrderId);
        }

        $newPaidOrdersCount = Order::whereIn('payment_status', ['PAID', 'SUCCESS'])
            ->where('status', 'CONFIRMED')
            ->count();

        $outOfStockMenusCount = MenuItem::where('stock', '<=', 0)->count();

        $recentActivities = collect();

        if ($activeOrders->isNotEmpty()) {
            $recentActivities = $activeOrders->take(3)->map(function (array $order) {
                return 'Pesanan ' . $order['display_id'] . ' (' . $order['customer_name'] . ') menunggu diproses dengan ' . (int) $order['item_count'] . ' item.';
            });
        }

        if ($recentActivities->isEmpty()) {
            $recentActivities = collect([
                'Belum ada aktivitas terbaru',
            ]);
        }

        return view('backoffice.dashboard.index', [
            'activeOrders' => $activeOrders,
            'selectedOrder' => $selectedOrder,
            'notifications' => [
                'new_paid_orders' => $newPaidOrdersCount,
                'out_of_stock_menus' => $outOfStockMenusCount,
            ],
            'recentActivities' => $recentActivities,
        ]);
    }

    private function normalizeOrderItems($items): array
    {
        if (is_array($items)) {
            return $items;
        }

        if (is_object($items)) {
            return (array) $items;
        }

        return [];
    }

    private function countOrderItems(array $items): int
    {
        return collect($items)->sum(function ($item) {
            if (is_array($item)) {
                if (isset($item['quantity']) && is_numeric($item['quantity'])) {
                    return max(1, (int) $item['quantity']);
                }

                return 1;
            }

            if (is_object($item) && isset($item->quantity) && is_numeric($item->quantity)) {
                return max(1, (int) $item->quantity);
            }

            return 1;
        });
    }

    private function humanizeStatus(string $status): string
    {
        return match ($status) {
            'PAYMENT_FAILED' => 'Pembayaran Gagal',
            'CONFIRMED' => 'Terkonfirmasi',
            'IN_QUEUE' => 'Dalam Antrean',
            'IN_PROGRESS' => 'Sedang Diproses',
            'DELIVERED' => 'Disajikan',
            default => ucfirst(strtolower(str_replace('_', ' ', $status))),
        };
    }
}
