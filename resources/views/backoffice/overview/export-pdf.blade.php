<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Overview Export PDF</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1e293b;
            font-size: 12px;
            margin: 24px;
        }

        .header {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .title {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            color: #0f172a;
        }

        .meta {
            margin-top: 6px;
            color: #475569;
            font-size: 11px;
        }

        .section {
            margin-top: 14px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 8px 0;
        }

        .kpi-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-left: -8px;
        }

        .kpi-card {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px;
            vertical-align: top;
        }

        .kpi-label {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin: 0;
        }

        .kpi-value {
            margin: 6px 0 0 0;
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
        }

        table.data th,
        table.data td {
            border: 1px solid #cbd5e1;
            padding: 7px 8px;
            text-align: left;
            font-size: 11px;
        }

        table.data th {
            background: #f1f5f9;
            color: #0f172a;
            font-weight: 700;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .chart-box {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 10px;
        }

        .chart-title {
            margin: 0 0 8px 0;
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }

        .chart-image {
            width: 100%;
            height: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .chart-empty {
            font-size: 11px;
            color: #64748b;
            padding: 8px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">Laporan Overview KedaiKlik</h1>
        <div class="meta">Periode: {{ $periodLabel }}</div>
        <div class="meta">Generated: {{ $generatedAt }}</div>
    </div>

    <div class="section">
        <h2 class="section-title">Ringkasan KPI</h2>
        <table class="kpi-grid">
            <tr>
                <td class="kpi-card" width="33%">
                    <p class="kpi-label">Total Menu</p>
                    <p class="kpi-value">{{ (int) data_get($overview, 'kpi.menus', 0) }}</p>
                </td>
                <td class="kpi-card" width="33%">
                    <p class="kpi-label">Total Order</p>
                    <p class="kpi-value">{{ (int) data_get($overview, 'kpi.orders', 0) }}</p>
                </td>
                <td class="kpi-card" width="33%">
                    <p class="kpi-label">Total User</p>
                    <p class="kpi-value">{{ (int) data_get($overview, 'kpi.users', 0) }}</p>
                </td>
            </tr>
            <tr>
                <td class="kpi-card">
                    <p class="kpi-label">Revenue Paid</p>
                    <p class="kpi-value">Rp {{ number_format((float) data_get($overview, 'kpi.revenue', 0), 0, ',', '.') }}</p>
                </td>
                <td class="kpi-card">
                    <p class="kpi-label">Avg Order Value</p>
                    <p class="kpi-value">Rp {{ number_format((float) data_get($overview, 'kpi.averageOrderValue', 0), 0, ',', '.') }}</p>
                </td>
                <td class="kpi-card">
                    <p class="kpi-label">Payment Success</p>
                    <p class="kpi-value">{{ number_format((float) data_get($overview, 'kpi.paymentSuccessRate', 0), 1, ',', '.') }}%</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Distribusi Status Order</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Status</th>
                    <th class="text-center">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $statusLabels = data_get($overview, 'charts.statusDistribution.labels', []);
                    $statusValues = data_get($overview, 'charts.statusDistribution.values', []);
                @endphp
                @forelse($statusLabels as $index => $statusLabel)
                    <tr>
                        <td>{{ $statusLabel }}</td>
                        <td class="text-center">{{ (int) ($statusValues[$index] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-center">Tidak ada data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Top Menu</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Nama Menu</th>
                    <th class="text-center">Terjual</th>
                </tr>
            </thead>
            <tbody>
                @forelse(data_get($overview, 'topMenus30Days', []) as $menu)
                    <tr>
                        <td>{{ $menu['name'] ?? '-' }}</td>
                        <td class="text-center">{{ (int) ($menu['count'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-center">Belum ada data menu terjual.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Tren Order & Revenue</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th class="text-center">Order</th>
                    <th class="text-right">Revenue Paid</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $trendLabels = data_get($overview, 'charts.orderTrend7Days.labels', []);
                    $trendOrders = data_get($overview, 'charts.orderTrend7Days.values', []);
                    $trendRevenue = data_get($overview, 'charts.revenueTrend7Days.values', []);
                @endphp
                @forelse($trendLabels as $index => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="text-center">{{ (int) ($trendOrders[$index] ?? 0) }}</td>
                        <td class="text-right">Rp {{ number_format((float) ($trendRevenue[$index] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center">Tidak ada data tren.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Occupancy Meja</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Total Meja</th>
                    <th>Terisi</th>
                    <th>Tersedia</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ (int) data_get($overview, 'tableOccupancy.totalTables', 0) }}</td>
                    <td>{{ (int) data_get($overview, 'tableOccupancy.occupiedTables', 0) }}</td>
                    <td>{{ (int) data_get($overview, 'tableOccupancy.availableTables', 0) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Visual Statistik (Sesuai Overview)</h2>

        <div class="chart-box">
            <p class="chart-title">{{ data_get($overview, 'meta.trendLabel', 'Tren 7 Hari') }} - Jumlah Order</p>
            @if (!empty($chartImages['lineOrder']))
                <img src="{{ $chartImages['lineOrder'] }}" alt="Chart jumlah order" class="chart-image">
            @else
                <p class="chart-empty">Chart belum tersedia karena ekstensi GD belum aktif.</p>
            @endif
        </div>

        <div class="chart-box">
            <p class="chart-title">Distribusi Status</p>
            @if (!empty($chartImages['donutStatus']))
                <img src="{{ $chartImages['donutStatus'] }}" alt="Chart distribusi status" class="chart-image">
            @else
                <p class="chart-empty">Chart belum tersedia karena ekstensi GD belum aktif.</p>
            @endif
        </div>

        <div class="chart-box">
            <p class="chart-title">{{ data_get($overview, 'meta.trendLabel', 'Tren 7 Hari') }} - Revenue Paid</p>
            @if (!empty($chartImages['barRevenue']))
                <img src="{{ $chartImages['barRevenue'] }}" alt="Chart revenue paid" class="chart-image">
            @else
                <p class="chart-empty">Chart belum tersedia karena ekstensi GD belum aktif.</p>
            @endif
        </div>
    </div>
</body>
</html>
