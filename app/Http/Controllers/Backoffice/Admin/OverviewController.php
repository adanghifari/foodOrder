<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OverviewController extends Controller
{
    private const ACTIVE_ORDER_STATUSES = ['CONFIRMED', 'IN_QUEUE', 'IN_PROGRESS'];
    private const PAID_STATUSES = ['PAID', 'SUCCESS', 'SETTLEMENT'];
    private const HOLD_ORDER_STATUSES = ['PENDING_PAYMENT'];
    private const HOLD_PAYMENT_STATUSES = ['PENDING'];

    public function indexPage(Request $request)
    {
        $this->validateFilterRequest($request);
        $filters = $this->resolveFilters($request);
        $overview = $this->buildOverviewData($filters);

        return view('backoffice.overview.index', [
            'overview' => $overview,
            'filters' => $filters,
        ]);
    }

    public function get(Request $request)
    {
        $this->validateFilterRequest($request);
        $filters = $this->resolveFilters($request);
        $overview = $this->buildOverviewData($filters);

        return response()->json([
            'status' => 'success',
            'message' => 'Overview retrieved',
            'data' => $overview,
            'filters' => $filters,
        ]);
    }

    public function exportPdf(Request $request)
    {
        $this->validateFilterRequest($request);
        $filters = $this->resolveFilters($request);
        $overview = $this->buildOverviewData($filters);
        $periodLabel = $this->buildPeriodLabel($filters);
        $generatedAt = now()->format('d M Y H:i');
        $chartImages = $this->buildPdfChartImages($overview);

        $pdf = Pdf::loadView('backoffice.overview.export-pdf', [
            'overview' => $overview,
            'filters' => $filters,
            'periodLabel' => $periodLabel,
            'generatedAt' => $generatedAt,
            'chartImages' => $chartImages,
        ])->setPaper('a4', 'portrait');

        $filename = $this->buildExportFilename($filters);

        return $pdf->download($filename);
    }

    private function buildOverviewData(array $filters): array
    {
        $totalMenus = MenuItem::count();
        $filteredOrdersQuery = $this->applyDateFilters(Order::query(), $filters);
        $totalOrders = (clone $filteredOrdersQuery)->count();
        $totalUsers = User::count();

        $paidOrders = (clone $filteredOrdersQuery)
            ->whereIn('payment_status', self::PAID_STATUSES)
            ->get(['total_price']);
        $paidOrdersCount = $paidOrders->count();
        $totalRevenue = (float) $paidOrders->sum('total_price');
        $averageOrderValue = $paidOrdersCount > 0 ? ($totalRevenue / $paidOrdersCount) : 0;
        $paymentSuccessRate = $totalOrders > 0 ? (($paidOrdersCount / $totalOrders) * 100) : 0;

        $trendOrders = $this->buildAdaptiveTrend($filters);

        $statusDistribution = [
            'labels' => ['Confirmed', 'In Queue', 'In Progress', 'Delivered'],
            'values' => [
                (clone $filteredOrdersQuery)->where('status', 'CONFIRMED')->count(),
                (clone $filteredOrdersQuery)->where('status', 'IN_QUEUE')->count(),
                (clone $filteredOrdersQuery)->where('status', 'IN_PROGRESS')->count(),
                (clone $filteredOrdersQuery)->where('status', 'DELIVERED')->count(),
            ],
        ];

        $topMenus = $this->buildTopMenus($filters);

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
            ->when(($filters['mode'] ?? '') === 'day' && $filters['date'] !== null, function ($query) use ($filters) {
                $query->whereDate('created_at', $filters['date']->format('Y-m-d'));
            })
            ->when(($filters['mode'] ?? '') === 'month' && $filters['month'] !== null, function ($query) use ($filters) {
                $query->whereMonth('created_at', $filters['month']);
                if ($filters['year'] !== null) {
                    $query->whereYear('created_at', $filters['year']);
                }
            })
            ->when(($filters['mode'] ?? '') === 'year' && $filters['year'] !== null, function ($query) use ($filters) {
                $query->whereYear('created_at', $filters['year']);
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
            'meta' => [
                'periodLabel' => $this->buildPeriodLabel($filters),
                'trendLabel' => $this->buildTrendLabel($filters),
            ],
        ];
    }

    private function buildAdaptiveTrend(array $filters): array
    {
        $mode = (string) ($filters['mode'] ?? 'none');

        if ($mode === 'day' && $filters['date'] instanceof Carbon) {
            return $this->buildDayTrendPer4Hours($filters['date']);
        }

        if ($mode === 'month' && !empty($filters['month'])) {
            $year = (int) ($filters['year'] ?? now()->year);
            return $this->buildMonthTrendPer7Days((int) $filters['month'], $year);
        }

        if ($mode === 'year' && !empty($filters['year'])) {
            return $this->buildYearTrendPer4Months((int) $filters['year']);
        }

        return $this->buildDefaultLast7DaysTrend();
    }

    private function buildDefaultLast7DaysTrend(): array
    {
        $start = now()->startOfDay()->subDays(6);
        $end = now()->endOfDay();
        $map = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $map[$key] = [
                'label' => $day->translatedFormat('d M'),
                'orders' => 0,
                'revenue' => 0,
            ];
        }

        $orders = Order::whereBetween('created_at', [$start, $end])->get(['created_at', 'total_price', 'payment_status']);
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

    private function buildDayTrendPer4Hours(Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $map = [];

        for ($h = 0; $h < 24; $h += 4) {
            $key = (string) $h;
            $map[$key] = [
                'label' => sprintf('%02d:00-%02d:59', $h, $h + 3),
                'orders' => 0,
                'revenue' => 0,
            ];
        }

        $orders = Order::whereBetween('created_at', [$start, $end])->get(['created_at', 'total_price', 'payment_status']);
        foreach ($orders as $order) {
            $createdAt = $this->toCarbon($order->created_at);
            if (!$createdAt) {
                continue;
            }
            $bucketHour = (int) floor(((int) $createdAt->hour) / 4) * 4;
            $key = (string) $bucketHour;
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

    private function buildMonthTrendPer7Days(int $month, int $year): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $ranges = [
            [1, 7],
            [8, 14],
            [15, 21],
            [22, 28],
            [29, (int) $end->day],
        ];

        $map = [];
        foreach ($ranges as [$from, $to]) {
            if ($from > $end->day) {
                continue;
            }
            $to = min($to, (int) $end->day);
            $key = $from . '-' . $to;
            $map[$key] = [
                'label' => $from . '-' . $to . ' ' . $start->translatedFormat('M'),
                'orders' => 0,
                'revenue' => 0,
            ];
        }

        $orders = Order::whereBetween('created_at', [$start, $end])->get(['created_at', 'total_price', 'payment_status']);
        foreach ($orders as $order) {
            $createdAt = $this->toCarbon($order->created_at);
            if (!$createdAt) {
                continue;
            }
            $day = (int) $createdAt->day;
            foreach ($map as $rangeKey => $bucket) {
                [$from, $to] = array_map('intval', explode('-', $rangeKey));
                if ($day >= $from && $day <= $to) {
                    $map[$rangeKey]['orders']++;
                    if (in_array((string) $order->payment_status, self::PAID_STATUSES, true)) {
                        $map[$rangeKey]['revenue'] += (float) ($order->total_price ?? 0);
                    }
                    break;
                }
            }
        }

        return array_values($map);
    }

    private function buildYearTrendPer4Months(int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfYear();
        $end = $start->copy()->endOfYear();
        $buckets = [
            ['from' => 1, 'to' => 4, 'label' => 'Jan-Apr'],
            ['from' => 5, 'to' => 8, 'label' => 'Mei-Agu'],
            ['from' => 9, 'to' => 12, 'label' => 'Sep-Des'],
        ];
        $map = [];
        foreach ($buckets as $bucket) {
            $key = $bucket['from'] . '-' . $bucket['to'];
            $map[$key] = [
                'label' => $bucket['label'],
                'orders' => 0,
                'revenue' => 0,
            ];
        }

        $orders = Order::whereBetween('created_at', [$start, $end])->get(['created_at', 'total_price', 'payment_status']);
        foreach ($orders as $order) {
            $createdAt = $this->toCarbon($order->created_at);
            if (!$createdAt) {
                continue;
            }
            $month = (int) $createdAt->month;
            foreach ($buckets as $bucket) {
                if ($month >= $bucket['from'] && $month <= $bucket['to']) {
                    $key = $bucket['from'] . '-' . $bucket['to'];
                    $map[$key]['orders']++;
                    if (in_array((string) $order->payment_status, self::PAID_STATUSES, true)) {
                        $map[$key]['revenue'] += (float) ($order->total_price ?? 0);
                    }
                    break;
                }
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

    private function buildTopMenus(array $filters): array
    {
        $counter = [];
        $orders = $this->applyDateFilters(Order::query(), $filters)->get(['items']);

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

    private function resolveFilters(Request $request): array
    {
        $modeRaw = trim((string) $request->query('mode', ''));
        $dayRaw = trim((string) $request->query('tanggal', ''));
        $monthRaw = trim((string) $request->query('bulan', ''));
        $yearRaw = trim((string) $request->query('tahun', ''));
        $mode = in_array($modeRaw, ['none', 'day', 'month', 'year'], true) ? $modeRaw : 'none';

        $day = ctype_digit($dayRaw) ? (int) $dayRaw : null;
        if ($day !== null && ($day < 1 || $day > 31)) {
            $day = null;
        }

        $month = ctype_digit($monthRaw) ? (int) $monthRaw : null;
        if ($month !== null && ($month < 1 || $month > 12)) {
            $month = null;
        }

        $year = ctype_digit($yearRaw) ? (int) $yearRaw : null;
        if ($year !== null && ($year < 2000 || $year > 2100)) {
            $year = null;
        }

        $date = null;
        if ($mode === 'day' && $day !== null && $month !== null && $year !== null && checkdate($month, $day, $year)) {
            $date = Carbon::create($year, $month, $day)->startOfDay();
        }

        if ($mode === 'month') {
            $day = null;
        }

        if ($mode === 'year') {
            $day = null;
            $month = null;
        }

        return [
            'mode' => $mode,
            'date' => $date,
            'day' => $day,
            'month' => $month,
            'year' => $year,
            'tanggal' => $day !== null ? (string) $day : '',
            'bulan' => $month !== null ? (string) $month : '',
            'tahun' => $year !== null ? (string) $year : '',
        ];
    }

    private function validateFilterRequest(Request $request): void
    {
        $request->validate([
            'mode' => ['nullable', Rule::in(['none', 'day', 'month', 'year'])],
            'tanggal' => ['nullable', 'integer', 'min:1', 'max:31', 'required_if:mode,day'],
            'bulan' => ['nullable', 'integer', 'min:1', 'max:12', 'required_if:mode,day,month'],
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100', 'required_if:mode,day,month,year'],
        ], [
            'tanggal.required_if' => 'Tanggal wajib dipilih untuk mode Spesifik Hari.',
            'bulan.required_if' => 'Bulan wajib dipilih untuk mode yang digunakan.',
            'tahun.required_if' => 'Tahun wajib dipilih untuk mode yang digunakan.',
        ]);

        $mode = (string) $request->query('mode', 'none');
        if ($mode === 'day') {
            $day = (int) $request->query('tanggal');
            $month = (int) $request->query('bulan');
            $year = (int) $request->query('tahun');
            if (!checkdate($month, $day, $year)) {
                throw ValidationException::withMessages([
                    'tanggal' => 'Kombinasi tanggal, bulan, dan tahun tidak valid.',
                ]);
            }
        }
    }

    private function applyDateFilters($query, array $filters)
    {
        if (($filters['mode'] ?? '') === 'day' && $filters['date'] !== null) {
            return $query->whereDate('created_at', $filters['date']->format('Y-m-d'));
        }

        if (($filters['mode'] ?? '') === 'month' && $filters['month'] !== null) {
            $query->whereMonth('created_at', $filters['month']);
            if ($filters['year'] !== null) {
                $query->whereYear('created_at', $filters['year']);
            }

            return $query;
        }

        if (($filters['mode'] ?? '') === 'year' && $filters['year'] !== null) {
            return $query->whereYear('created_at', $filters['year']);
        }

        return $query;
    }

    private function buildPeriodLabel(array $filters): string
    {
        if (($filters['mode'] ?? '') === 'day' && $filters['date'] instanceof Carbon) {
            return 'Harian ' . $filters['date']->format('d M Y');
        }

        if (($filters['mode'] ?? '') === 'month' && !empty($filters['month'])) {
            $monthDate = Carbon::create((int) ($filters['year'] ?? now()->year), (int) $filters['month'], 1);
            return 'Bulanan ' . $monthDate->translatedFormat('F Y');
        }

        if (($filters['mode'] ?? '') === 'year' && !empty($filters['year'])) {
            return 'Tahunan ' . (string) $filters['year'];
        }

        return 'Semua Periode';
    }

    private function buildExportFilename(array $filters): string
    {
        $mode = (string) ($filters['mode'] ?? 'none');
        $timestamp = now()->format('Ymd-His');

        if ($mode === 'day' && $filters['date'] instanceof Carbon) {
            return 'overview-harian-' . $filters['date']->format('Y-m-d') . '-' . $timestamp . '.pdf';
        }

        if ($mode === 'month' && !empty($filters['month'])) {
            $year = (int) ($filters['year'] ?? now()->year);
            $month = str_pad((string) ((int) $filters['month']), 2, '0', STR_PAD_LEFT);
            return 'overview-bulanan-' . $year . '-' . $month . '-' . $timestamp . '.pdf';
        }

        if ($mode === 'year' && !empty($filters['year'])) {
            return 'overview-tahunan-' . (string) $filters['year'] . '-' . $timestamp . '.pdf';
        }

        return 'overview-semua-periode-' . $timestamp . '.pdf';
    }

    private function buildTrendLabel(array $filters): string
    {
        if (($filters['mode'] ?? '') === 'day' && $filters['date'] instanceof Carbon) {
            return 'Tren Harian (per 4 jam)';
        }

        if (($filters['mode'] ?? '') === 'month' && !empty($filters['month'])) {
            return 'Tren Bulanan (per 7 hari)';
        }

        if (($filters['mode'] ?? '') === 'year' && !empty($filters['year'])) {
            return 'Tren Tahunan (per 4 bulan)';
        }

        return 'Tren 7 Hari';
    }

    private function buildPdfChartImages(array $overview): array
    {
        if (!function_exists('imagecreatetruecolor')) {
            return [
                'lineOrder' => null,
                'barRevenue' => null,
                'donutStatus' => null,
            ];
        }

        return [
            'lineOrder' => $this->renderLineChartPng(
                (array) data_get($overview, 'charts.orderTrend7Days.labels', []),
                array_map('floatval', (array) data_get($overview, 'charts.orderTrend7Days.values', [])),
                'Jumlah Order',
                [37, 99, 235]
            ),
            'barRevenue' => $this->renderBarChartPng(
                (array) data_get($overview, 'charts.revenueTrend7Days.labels', []),
                array_map('floatval', (array) data_get($overview, 'charts.revenueTrend7Days.values', [])),
                'Revenue Paid',
                [16, 185, 129]
            ),
            'donutStatus' => $this->renderDonutChartPng(
                (array) data_get($overview, 'charts.statusDistribution.labels', []),
                array_map('floatval', (array) data_get($overview, 'charts.statusDistribution.values', []))
            ),
        ];
    }

    private function renderLineChartPng(array $labels, array $values, string $title, array $rgb): ?string
    {
        $w = 940;
        $h = 360;
        $mLeft = 62;
        $mRight = 24;
        $mTop = 42;
        $mBottom = 56;
        $plotW = $w - $mLeft - $mRight;
        $plotH = $h - $mTop - $mBottom;

        $img = imagecreatetruecolor($w, $h);
        imageantialias($img, true);
        $bg = imagecolorallocate($img, 255, 255, 255);
        $axis = imagecolorallocate($img, 148, 163, 184);
        $grid = imagecolorallocate($img, 226, 232, 240);
        $text = imagecolorallocate($img, 51, 65, 85);
        $line = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        $fill = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], 95);
        imagefill($img, 0, 0, $bg);

        imagestring($img, 5, 16, 12, $title, $text);
        imageline($img, $mLeft, $mTop, $mLeft, $mTop + $plotH, $axis);
        imageline($img, $mLeft, $mTop + $plotH, $mLeft + $plotW, $mTop + $plotH, $axis);

        $count = max(count($values), 1);
        $maxVal = max($values ?: [0]);
        if ($maxVal <= 0) {
            $maxVal = 1;
        }

        for ($i = 0; $i <= 4; $i++) {
            $yy = (int) ($mTop + ($plotH * $i / 4));
            imageline($img, $mLeft, $yy, $mLeft + $plotW, $yy, $grid);
        }

        $points = [];
        foreach ($values as $i => $value) {
            $x = (int) ($mLeft + ($plotW * ($count === 1 ? 0.5 : ($i / ($count - 1)))));
            $y = (int) ($mTop + $plotH - (($value / $maxVal) * $plotH));
            $points[] = [$x, $y];
            if (isset($labels[$i])) {
                imagestring($img, 2, $x - 16, $mTop + $plotH + 10, (string) $labels[$i], $text);
            }
        }

        if (count($points) > 1) {
            for ($i = 0; $i < count($points) - 1; $i++) {
                imageline($img, $points[$i][0], $points[$i][1], $points[$i + 1][0], $points[$i + 1][1], $line);
            }
            foreach ($points as [$px, $py]) {
                imagefilledellipse($img, $px, $py, 8, 8, $line);
                imageellipse($img, $px, $py, 10, 10, $bg);
            }
        } elseif (count($points) === 1) {
            imagefilledellipse($img, $points[0][0], $points[0][1], 8, 8, $line);
        }

        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode((string) $data);
    }

    private function renderBarChartPng(array $labels, array $values, string $title, array $rgb): ?string
    {
        $w = 940;
        $h = 360;
        $mLeft = 62;
        $mRight = 24;
        $mTop = 42;
        $mBottom = 56;
        $plotW = $w - $mLeft - $mRight;
        $plotH = $h - $mTop - $mBottom;

        $img = imagecreatetruecolor($w, $h);
        imageantialias($img, true);
        $bg = imagecolorallocate($img, 255, 255, 255);
        $axis = imagecolorallocate($img, 148, 163, 184);
        $grid = imagecolorallocate($img, 226, 232, 240);
        $text = imagecolorallocate($img, 51, 65, 85);
        $bar = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        imagefill($img, 0, 0, $bg);

        imagestring($img, 5, 16, 12, $title, $text);
        imageline($img, $mLeft, $mTop, $mLeft, $mTop + $plotH, $axis);
        imageline($img, $mLeft, $mTop + $plotH, $mLeft + $plotW, $mTop + $plotH, $axis);

        $count = max(count($values), 1);
        $maxVal = max($values ?: [0]);
        if ($maxVal <= 0) {
            $maxVal = 1;
        }

        for ($i = 0; $i <= 4; $i++) {
            $yy = (int) ($mTop + ($plotH * $i / 4));
            imageline($img, $mLeft, $yy, $mLeft + $plotW, $yy, $grid);
        }

        $slot = $plotW / $count;
        $barW = max((int) ($slot * 0.5), 10);
        foreach ($values as $i => $value) {
            $x1 = (int) ($mLeft + ($i * $slot) + (($slot - $barW) / 2));
            $x2 = $x1 + $barW;
            $y2 = $mTop + $plotH;
            $y1 = (int) ($y2 - (($value / $maxVal) * $plotH));
            imagefilledrectangle($img, $x1, $y1, $x2, $y2, $bar);
            if (isset($labels[$i])) {
                imagestring($img, 2, $x1 - 8, $mTop + $plotH + 10, (string) $labels[$i], $text);
            }
        }

        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode((string) $data);
    }

    private function renderDonutChartPng(array $labels, array $values): ?string
    {
        $w = 940;
        $h = 360;
        $img = imagecreatetruecolor($w, $h);
        imageantialias($img, true);
        $bg = imagecolorallocate($img, 255, 255, 255);
        $text = imagecolorallocate($img, 51, 65, 85);
        imagefill($img, 0, 0, $bg);

        imagestring($img, 5, 16, 12, 'Distribusi Status', $text);

        $paletteRgb = [
            [245, 158, 11],
            [249, 115, 22],
            [59, 130, 246],
            [16, 185, 129],
            [100, 116, 139],
            [139, 92, 246],
        ];
        $colors = [];
        foreach ($paletteRgb as [$r, $g, $b]) {
            $colors[] = imagecolorallocate($img, $r, $g, $b);
        }

        $total = array_sum($values);
        if ($total <= 0) {
            $total = 1;
        }

        $cx = 260;
        $cy = 190;
        $diameter = 220;
        $start = 0.0;
        foreach ($values as $i => $v) {
            $end = $start + ((float) $v / $total) * 360.0;
            imagefilledarc($img, $cx, $cy, $diameter, $diameter, (int) $start, (int) $end, $colors[$i % count($colors)], IMG_ARC_PIE);
            $start = $end;
        }
        imagefilledellipse($img, $cx, $cy, 110, 110, $bg);

        foreach ($labels as $i => $label) {
            $yy = 100 + ($i * 28);
            imagefilledrectangle($img, 500, $yy, 512, $yy + 12, $colors[$i % count($colors)]);
            $value = (int) ($values[$i] ?? 0);
            imagestring($img, 3, 520, $yy, (string) $label . ' (' . $value . ')', $text);
        }

        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode((string) $data);
    }
}
