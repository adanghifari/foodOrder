<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk Pembelian</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; margin: 0; padding: 0; background: #f8fafc; }
        .page { max-width: 760px; margin: 18px auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 14px; overflow: hidden; }
        .header { background: #c8641e; color: #fff; padding: 20px; }
        .brand { font-size: 11px; letter-spacing: .08em; font-weight: 700; text-transform: uppercase; opacity: .9; }
        .title { margin-top: 4px; font-size: 34px; font-weight: 800; }
        .subtitle { margin-top: 6px; font-size: 14px; opacity: .95; }
        .invoice { margin-top: 10px; font-size: 13px; font-weight: 700; }
        .content { padding: 16px; }
        .card { border: 1px solid #e5e7eb; border-radius: 14px; background: #f8fafc; padding: 12px; margin-bottom: 12px; }
        .row { width: 100%; border-collapse: collapse; }
        .row td { padding: 4px 0; vertical-align: top; }
        .label { color: #6b7280; width: 38%; }
        .value { text-align: right; font-weight: 700; color: #1f2937; }
        .status-wrap { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin-left: -10px; margin-bottom: 12px; }
        .status { border-radius: 12px; border: 1px solid #cbd5e1; padding: 10px; }
        .status-title { font-size: 10px; text-transform: uppercase; font-weight: 700; margin: 0; }
        .status-value { margin: 6px 0 0; font-size: 22px; font-weight: 800; }
        .paid { background: #d1fae5; border-color: #a7f3d0; color: #047857; }
        .pending { background: #fef3c7; border-color: #fde68a; color: #b45309; }
        .order { background: #dbeafe; border-color: #bfdbfe; color: #1d4ed8; }
        .section-title { font-size: 15px; font-weight: 800; margin: 0 0 8px 0; }
        .list { border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .item { border-bottom: 1px solid #f1f5f9; padding: 10px 12px; }
        .item:last-child { border-bottom: none; }
        .item-row { width: 100%; border-collapse: collapse; }
        .item-name { font-size: 14px; font-weight: 700; color: #1f2937; }
        .item-meta { font-size: 11px; color: #6b7280; margin-top: 2px; }
        .item-total { text-align: right; font-size: 14px; font-weight: 800; color: #374151; }
        .summary { border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px; margin-top: 12px; }
        .summary-row { width: 100%; border-collapse: collapse; }
        .summary-row td { padding: 4px 0; }
        .summary-label { color: #6b7280; }
        .summary-value { text-align: right; font-weight: 700; color: #374151; }
        .summary-total { border-top: 1px solid #e5e7eb; margin-top: 6px; padding-top: 6px; }
        .summary-total .summary-label { font-size: 18px; font-weight: 800; color: #1f2937; }
        .summary-total .summary-value { font-size: 18px; font-weight: 900; color: #c8641e; }
    </style>
</head>
<body>
    @php
        $paymentStatusRaw = strtoupper((string) ($order->payment_status ?? 'PENDING'));
        $paymentSubtitle = match ($paymentStatusRaw) {
            'PAID', 'SUCCESS', 'SETTLEMENT' => 'Terima kasih, pembayaran kamu sudah kami terima.',
            'PENDING' => 'Menunggu Pembayaran.',
            'FAILED', 'CANCELED' => 'Pembayaran gagal.',
            default => 'Status pembayaran sedang diperbarui.',
        };
    @endphp
    <main class="page">
        <header class="header">
            <div class="brand">KEDAIKLIK</div>
            <div class="title">Struk Pembelian</div>
            <div class="subtitle">{{ $paymentSubtitle }}</div>
            @if (($invoiceCount ?? 0) > 1)
                <div class="invoice">Invoice {{ (int) (($invoiceIndex ?? 0) + 1) }} dari {{ (int) ($invoiceCount ?? 0) }}</div>
            @endif
        </header>

        <section class="content">
            <div class="card">
                <table class="row">
                    <tr><td class="label">Order ID</td><td class="value">{{ $displayOrderId }}</td></tr>
                    <tr><td class="label">Nama Pemesan</td><td class="value">{{ (string) ($order->customer_name ?? '-') !== '' ? (string) ($order->customer_name ?? '-') : '-' }}</td></tr>
                    <tr><td class="label">Email Pemesan</td><td class="value">{{ (string) ($order->customer_email ?? '-') !== '' ? (string) ($order->customer_email ?? '-') : '-' }}</td></tr>
                    <tr><td class="label">Midtrans ID</td><td class="value">{{ $order->midtrans_order_id ?? '-' }}</td></tr>
                    <tr><td class="label">Meja</td><td class="value">{{ (int) ($order->table_number ?? 0) }}</td></tr>
                    <tr><td class="label">Waktu Bayar</td><td class="value">{{ $paidAtLabel }}</td></tr>
                    <tr><td class="label">Metode Bayar</td><td class="value">{{ $paymentTypeLabel }}</td></tr>
                    <tr><td class="label">Nomor VA</td><td class="value">{{ $vaNumber !== '' ? $vaNumber : '-' }}</td></tr>
                </table>
            </div>

            <table class="status-wrap">
                <tr>
                    <td width="50%">
                        <div class="status {{ $paymentLabel === 'LUNAS' ? 'paid' : 'pending' }}">
                            <p class="status-title">Status Payment</p>
                            <p class="status-value">{{ $paymentLabel }}</p>
                        </div>
                    </td>
                    <td width="50%">
                        <div class="status order">
                            <p class="status-title">Status Pesanan</p>
                            <p class="status-value">{{ $orderLabel }}</p>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="card">
                <h2 class="section-title">Detail Item</h2>
                <div class="list">
                    @forelse ($items as $item)
                        <div class="item">
                            <table class="item-row">
                                <tr>
                                    <td>
                                        <div class="item-name">{{ $item['name'] }}</div>
                                        <div class="item-meta">{{ $item['qty'] }} x Rp {{ number_format($item['unit_price'], 0, ',', '.') }}</div>
                                    </td>
                                    <td class="item-total">Rp {{ number_format($item['line_total'], 0, ',', '.') }}</td>
                                </tr>
                            </table>
                        </div>
                    @empty
                        <div class="item">Tidak ada item.</div>
                    @endforelse
                </div>
            </div>

            <div class="summary">
                <table class="summary-row">
                    <tr><td class="summary-label">Subtotal</td><td class="summary-value">Rp {{ number_format($subtotal, 0, ',', '.') }}</td></tr>
                    <tr><td class="summary-label">Biaya Layanan</td><td class="summary-value">Rp {{ number_format($serviceFee, 0, ',', '.') }}</td></tr>
                </table>
                <div class="summary-total">
                    <table class="summary-row">
                        <tr><td class="summary-label">Total Pembayaran</td><td class="summary-value">Rp {{ number_format($total, 0, ',', '.') }}</td></tr>
                    </table>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
