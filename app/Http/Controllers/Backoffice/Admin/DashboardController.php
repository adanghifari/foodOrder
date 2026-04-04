<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;

class DashboardController extends Controller
{
    public function index()
    {
        $orders = Order::orderBy('_id', 'desc')->limit(8)->get();

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

            return [
                'id' => (string) $order->_id,
                'display_id' => 'ORD-' . strtoupper(substr((string) $order->_id, -6)),
                'status' => (string) ($order->status ?? 'UNKNOWN'),
                'items' => $itemRows,
            ];
        });

        $newPaidOrdersCount = Order::whereIn('payment_status', ['PAID', 'SUCCESS'])
            ->where('status', 'CONFIRMED')
            ->count();

        $outOfStockMenusCount = MenuItem::where('stock', '<=', 0)->count();

        $recentActivities = collect();

        if ($activeOrders->isNotEmpty()) {
            $recentActivities = $activeOrders->take(3)->map(function (array $order) {
                return 'Pesanan ' . $order['display_id'] . ' berstatus ' . $this->humanizeStatus($order['status']);
            });
        }

        if ($recentActivities->isEmpty()) {
            $recentActivities = collect([
                'Belum ada aktivitas terbaru',
            ]);
        }

        return view('backoffice.dashboard.index', [
            'activeOrders' => $activeOrders,
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

    private function humanizeStatus(string $status): string
    {
        return match ($status) {
            'CONFIRMED' => 'Pending',
            'IN_QUEUE' => 'Dalam Antrian',
            'IN_PROGRESS' => 'Diproses',
            'DELIVERED' => 'Siap Diantar',
            default => ucfirst(strtolower(str_replace('_', ' ', $status))),
        };
    }
}
