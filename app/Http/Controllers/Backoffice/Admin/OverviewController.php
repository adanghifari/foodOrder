<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;

class OverviewController extends Controller
{
    private const ACTIVE_ORDER_STATUSES = ['CONFIRMED', 'IN_QUEUE', 'IN_PROGRESS'];
    private const PAID_STATUSES = ['PAID', 'SUCCESS', 'SETTLEMENT'];
    private const HOLD_ORDER_STATUSES = ['PENDING_PAYMENT'];
    private const HOLD_PAYMENT_STATUSES = ['PENDING'];

    public function indexPage()
    {
        $overview = $this->buildOverviewData();

        return view('backoffice.overview.index', [
            'overview' => $overview,
        ]);
    }

    public function get()
    {
        $overview = $this->buildOverviewData();

        return response()->json([
            'status' => 'success',
            'message' => 'Overview retrieved',
            'data' => $overview,
        ]);
    }

    private function buildOverviewData(): array
    {
        $totalMenus = MenuItem::count();
        $totalOrders = Order::count();
        $totalUsers = User::count();

        $paidOrders = Order::whereIn('payment_status', self::PAID_STATUSES)->get(['total_price']);
        $paidOrdersCount = $paidOrders->count();
        $totalRevenue = (float) $paidOrders->sum('total_price');
        $averageOrderValue = $paidOrdersCount > 0 ? ($totalRevenue / $paidOrdersCount) : 0;
        $paymentSuccessRate = $totalOrders > 0 ? (($paidOrdersCount / $totalOrders) * 100) : 0;

        $trendOrders = $this->build7DayTrend();

        $statusDistribution = [
            'labels' => ['Confirmed', 'In Queue', 'In Progress', 'Delivered'],
            'values' => [
                Order::where('status', 'CONFIRMED')->count(),
                Order::where('status', 'IN_QUEUE')->count(),
                Order::where('status', 'IN_PROGRESS')->count(),
                Order::where('status', 'DELIVERED')->count(),
            ],
        ];

        $topMenus = $this->buildTopMenus();

        $knownTableIds = config('tables.known_table_ids', []);
        if (!is_array($knownTableIds) || count($knownTableIds) === 0) {
            $knownTableIds = range(
                (int) config('tables.min_table_id', 1),
                (int) config('tables.max_table_id', 100)
            );
        }

        $occupiedTables = Order::where(function ($query) {
                $query->where(function ($paidFlowQuery) {
                    $paidFlowQuery->whereIn('payment_status', self::PAID_STATUSES)
                        ->whereIn('status', self::ACTIVE_ORDER_STATUSES);
                })->orWhere(function ($pendingFlowQuery) {
                    $pendingFlowQuery->whereIn('payment_status', self::HOLD_PAYMENT_STATUSES)
                        ->whereIn('status', self::HOLD_ORDER_STATUSES)
                        ->whereNull('table_cleared_at');
                });
            })
            ->whereIn('table_number', $knownTableIds)
            ->distinct('table_number')
            ->count('table_number');

        return [
            'kpi' => [
                'menus' => $totalMenus,
                'orders' => $totalOrders,
                'users' => $totalUsers,
                'revenue' => $totalRevenue,
                'averageOrderValue' => $averageOrderValue,
                'paymentSuccessRate' => round($paymentSuccessRate, 1),
            ],
            'charts' => [
                'orderTrend7Days' => [
                    'labels' => array_column($trendOrders, 'label'),
                    'values' => array_column($trendOrders, 'orders'),
                ],
                'revenueTrend7Days' => [
                    'labels' => array_column($trendOrders, 'label'),
                    'values' => array_column($trendOrders, 'revenue'),
                ],
                'statusDistribution' => $statusDistribution,
            ],
            'topMenus30Days' => $topMenus,
            'tableOccupancy' => [
                'totalTables' => count($knownTableIds),
                'occupiedTables' => $occupiedTables,
                'availableTables' => max(count($knownTableIds) - $occupiedTables, 0),
            ],
        ];
    }

    private function build7DayTrend(): array
    {
        $start = now()->startOfDay()->subDays(6);
        $map = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $map[$key] = [
                'label' => $day->translatedFormat('D'),
                'orders' => 0,
                'revenue' => 0,
            ];
        }

        $orders = Order::where('created_at', '>=', $start)->get([
            'created_at',
            'total_price',
            'payment_status',
        ]);

        foreach ($orders as $order) {
            $createdAt = $this->toCarbon($order->created_at);
            if (!$createdAt) {
                continue;
            }

            $key = $createdAt->format('Y-m-d');
            if (!isset($map[$key])) {
                continue;
            }

            $map[$key]['orders']++;

            if (in_array((string) $order->payment_status, self::PAID_STATUSES, true)) {
                $map[$key]['revenue'] += (float) ($order->total_price ?? 0);
            }
        }

        return array_values($map);
    }

    private function toCarbon($value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value);
    }

    private function buildTopMenus(): array
    {
        $counter = [];
        $orders = Order::where('created_at', '>=', now()->subDays(30))->get(['items']);

        foreach ($orders as $order) {
            foreach ($this->normalizeOrderItems($order->items) as $item) {
                $name = trim((string) ($item['name'] ?? '-'));
                if ($name === '') {
                    $name = '-';
                }

                $qty = (int) ($item['quantity'] ?? 1);
                if ($qty < 1) {
                    $qty = 1;
                }

                if (!isset($counter[$name])) {
                    $counter[$name] = 0;
                }

                $counter[$name] += $qty;
            }
        }

        arsort($counter);

        return collect($counter)
            ->take(5)
            ->map(function ($count, $name) {
                return [
                    'name' => (string) $name,
                    'count' => (int) $count,
                ];
            })
            ->values()
            ->toArray();
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
}
