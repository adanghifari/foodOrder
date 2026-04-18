<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>KedaiKlik - Struk Pembayaran</title>
    <link rel="icon" type="image/png" href="/images/KedaiKlikLogo.png">
    <link rel="apple-touch-icon" href="/images/KedaiKlikLogo.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex justify-center p-4">
    <x-notification-center />
    @if (empty($order))
        <main class="w-full max-w-md bg-white min-h-screen shadow-2xl rounded-3xl overflow-hidden border border-gray-100 flex flex-col">
            <header class="bg-[#C8641E] text-white px-6 py-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-orange-100">KedaiKlik</p>
                <h1 class="text-2xl font-extrabold">Struk Pembelian</h1>
                <p class="text-sm text-orange-100 mt-1">Informasi struk untuk sesi browser ini.</p>
            </header>

            <section class="px-6 py-10 flex-1 flex flex-col items-center justify-center text-center">
                <div class="w-14 h-14 rounded-2xl bg-orange-100 text-[#C8641E] flex items-center justify-center mb-4">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-extrabold text-gray-800">Struk Belum Tersedia</h2>
                <p class="text-sm text-gray-500 mt-2 max-w-xs">Belum ada struk aktif di browser ini.</p>
            </section>

            <footer class="px-6 pb-6">
                <a href="/menu" class="w-full inline-flex items-center justify-center rounded-2xl bg-[#C8641E] hover:bg-[#A85318] text-white font-bold px-5 py-3 transition">Kembali ke Menu</a>
            </footer>
        </main>
    @else
    @php
        $paymentStatus = strtoupper((string) ($order->payment_status ?? 'PENDING'));
        $orderStatus = strtoupper((string) ($order->status ?? 'CONFIRMED'));
        $paymentPayload = is_array($order->payment_payload ?? null) ? $order->payment_payload : [];
        $paymentTypeRaw = trim((string) ($order->payment_type ?? ''));
        $paymentTypeLabel = match (strtolower($paymentTypeRaw)) {
            'bank_transfer' => 'Bank Transfer',
            'echannel' => 'Mandiri Bill',
            'cstore' => 'Convenience Store',
            'gopay' => 'GoPay',
            'qris' => 'QRIS',
            default => $paymentTypeRaw !== '' ? ucwords(str_replace('_', ' ', $paymentTypeRaw)) : '-',
        };
        $vaNumber = '-';

        if (!empty($paymentPayload['va_numbers']) && is_array($paymentPayload['va_numbers'])) {
            $firstVa = $paymentPayload['va_numbers'][0] ?? null;
            if (is_array($firstVa) && !empty($firstVa['va_number'])) {
                $bankLabel = !empty($firstVa['bank']) ? strtoupper((string) $firstVa['bank']) . ' ' : '';
                $vaNumber = $bankLabel . (string) $firstVa['va_number'];
            }
        } elseif (!empty($paymentPayload['permata_va_number'])) {
            $vaNumber = 'PERMATA ' . (string) $paymentPayload['permata_va_number'];
        } elseif (!empty($paymentPayload['bill_key']) || !empty($paymentPayload['biller_code'])) {
            $billerCode = (string) ($paymentPayload['biller_code'] ?? '-');
            $billKey = (string) ($paymentPayload['bill_key'] ?? '-');
            $vaNumber = trim($billerCode . ' / ' . $billKey, ' /');
        } elseif (!empty($paymentPayload['payment_code'])) {
            $vaNumber = (string) $paymentPayload['payment_code'];
        }

        $canResumePaymentMethod = $paymentStatus === 'PENDING';
        $canCancelPayment = $paymentStatus === 'PENDING';
        $resumePaymentLabel = $paymentTypeRaw === ''
            ? 'Pilih Metode Pembayaran'
            : 'Ganti Metode Pembayaran';

        $paymentLabel = match ($paymentStatus) {
            'PAID', 'SUCCESS', 'SETTLEMENT' => 'LUNAS',
            'FAILED' => 'GAGAL',
            'CANCELED' => 'DIBATALKAN',
            'EXPIRED' => 'KEDALUWARSA',
            default => 'MENUNGGU',
        };

        $orderLabel = match ($orderStatus) {
            'PENDING_PAYMENT' => 'Menunggu Pembayaran',
            'PAYMENT_FAILED' => 'Pembayaran Gagal',
            'CONFIRMED' => 'Terkonfirmasi',
            'IN_QUEUE' => 'Dalam Antrean',
            'IN_PROGRESS' => 'Sedang Diproses',
            'DELIVERED' => 'Disajikan',
            default => ucwords(strtolower(str_replace('_', ' ', $orderStatus))),
        };

        $paymentClass = in_array($paymentStatus, ['PAID', 'SUCCESS', 'SETTLEMENT'], true)
            ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
            : 'bg-amber-100 text-amber-700 border-amber-200';

        $displayOrderId = 'ORD-' . strtoupper(substr((string) $order->_id, -6));
        $isPaidPayment = in_array($paymentStatus, ['PAID', 'SUCCESS', 'SETTLEMENT'], true);
        $paidAtIso = $isPaidPayment ? optional($order->paid_at)->toIso8601String() : null;
        $paidAtLabel = $isPaidPayment && $order->paid_at
            ? $order->paid_at->copy()->setTimezone(config('app.timezone'))->format('d M Y H:i')
            : '-';
    @endphp

    <main class="w-full max-w-md bg-white min-h-screen shadow-2xl rounded-3xl overflow-hidden border border-gray-100">
        <header class="bg-[#C8641E] text-white px-6 py-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-orange-100">KedaiKlik</p>
                    <h1 class="text-2xl font-extrabold">Struk Pembelian</h1>
                    <p class="text-sm text-orange-100 mt-1">Terima kasih, pembayaran kamu sudah kami terima.</p>
                </div>
                @if (($invoiceCount ?? 0) > 1)
                    <div class="flex items-center gap-2">
                        @php
                            $currentIndex = (int) ($invoiceIndex ?? 0);
                            $totalInvoices = (int) ($invoiceCount ?? 0);
                            $hasPrev = $currentIndex > 0;
                            $hasNext = $currentIndex < ($totalInvoices - 1);
                        @endphp

                        <a
                            href="{{ $hasPrev ? '/kedai/pembayaran/struk?invoice_index=' . ($currentIndex - 1) : '#' }}"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-white/40 {{ $hasPrev ? 'hover:bg-white/20' : 'opacity-40 pointer-events-none' }} transition"
                            aria-label="Invoice sebelumnya"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                        </a>

                        <a
                            href="{{ $hasNext ? '/kedai/pembayaran/struk?invoice_index=' . ($currentIndex + 1) : '#' }}"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-white/40 {{ $hasNext ? 'hover:bg-white/20' : 'opacity-40 pointer-events-none' }} transition"
                            aria-label="Invoice berikutnya"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                @endif
            </div>

            @if (($invoiceCount ?? 0) > 1)
                <p class="mt-3 text-xs font-semibold text-orange-100">Invoice {{ (int) (($invoiceIndex ?? 0) + 1) }} dari {{ (int) ($invoiceCount ?? 0) }}</p>
            @endif
        </header>

        <section class="px-6 py-5 space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 space-y-2">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Order ID</span>
                    <span class="font-extrabold text-gray-800">{{ $displayOrderId }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Midtrans ID</span>
                    <span class="font-semibold text-gray-700 text-right">{{ $order->midtrans_order_id ?? '-' }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Meja</span>
                    <span class="font-bold text-gray-800">{{ (int) ($order->table_number ?? 0) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Waktu Bayar</span>
                    <span class="font-semibold text-gray-700" @if($paidAtIso) data-local-datetime="{{ $paidAtIso }}" @endif>{{ $paidAtLabel }}</span>
                </div>
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="text-gray-500">Metode Bayar</span>
                    <span class="font-semibold text-gray-700 text-right">{{ $paymentTypeLabel }}</span>
                </div>
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="text-gray-500">Nomor VA</span>
                    <span class="font-semibold text-gray-700 text-right">{{ $vaNumber !== '' ? $vaNumber : '-' }}</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-2xl border px-3 py-3 {{ $paymentClass }}">
                    <p class="text-xs font-bold uppercase">Status Payment</p>
                    <p class="text-sm font-extrabold mt-1">{{ $paymentLabel }}</p>
                </div>
                <div class="rounded-2xl border border-blue-200 bg-blue-100 text-blue-700 px-3 py-3">
                    <p class="text-xs font-bold uppercase">Status Pesanan</p>
                    <p class="text-sm font-extrabold mt-1">{{ $orderLabel }}</p>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                    <h2 class="font-bold text-gray-800">Detail Item</h2>
                </div>
                <ul class="divide-y divide-gray-100">
                    @forelse ($items as $item)
                        <li class="px-4 py-3 flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-gray-800 text-sm">{{ $item['name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $item['qty'] }} x Rp {{ number_format($item['unit_price'], 0, ',', '.') }}</p>
                            </div>
                            <p class="font-bold text-gray-700 text-sm">Rp {{ number_format($item['line_total'], 0, ',', '.') }}</p>
                        </li>
                    @empty
                        <li class="px-4 py-4 text-sm text-gray-500">Tidak ada item.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-4 space-y-2">
                <div class="flex items-center justify-between text-sm text-gray-600">
                    <span>Subtotal</span>
                    <span class="font-semibold">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between text-sm text-gray-600">
                    <span>Biaya Layanan</span>
                    <span class="font-semibold">Rp {{ number_format($serviceFee, 0, ',', '.') }}</span>
                </div>
                <div class="border-t border-gray-200 pt-2 flex items-center justify-between">
                    <span class="font-bold text-gray-800">Total Pembayaran</span>
                    <span class="font-black text-[#C8641E]">Rp {{ number_format($total, 0, ',', '.') }}</span>
                </div>
            </div>
        </section>

        <footer class="px-6 pb-6 pt-2">
            <div class="space-y-3">
                @if ($canResumePaymentMethod)
                    <a href="/kedai/pembayaran/{{ urlencode((string) $order->_id) }}/pilih-metode" class="w-full inline-flex items-center justify-center rounded-2xl border border-[#C8641E] bg-orange-50 hover:bg-orange-100 text-[#C8641E] font-bold px-5 py-3 transition">
                        {{ $resumePaymentLabel }}
                    </a>
                @endif
                @if ($canCancelPayment)
                    <form method="POST" action="/kedai/pembayaran/{{ urlencode((string) $order->_id) }}/batalkan">
                        @csrf
                        <button type="submit" class="w-full inline-flex items-center justify-center rounded-2xl border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 font-bold px-5 py-3 transition">
                            Batalkan Pembayaran
                        </button>
                    </form>
                @endif
                <a href="/menu" class="w-full inline-flex items-center justify-center rounded-2xl bg-[#C8641E] hover:bg-[#A85318] text-white font-bold px-5 py-3 transition">Kembali ke Menu</a>
            </div>
        </footer>
    </main>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const emptyReceiptMessage = @json($emptyReceiptMessage ?? null);
            if (typeof emptyReceiptMessage === 'string' && emptyReceiptMessage.trim() !== '' && window.KedaiKlikNotify && typeof window.KedaiKlikNotify.show === 'function') {
                window.KedaiKlikNotify.show({
                    type: 'warning',
                    title: 'Notifikasi Struk',
                    message: emptyReceiptMessage,
                    duration: 4800,
                });
            }

            const formatter = new Intl.DateTimeFormat('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });

            document.querySelectorAll('[data-local-datetime]').forEach((element) => {
                const rawValue = element.getAttribute('data-local-datetime');
                const parsed = rawValue ? new Date(rawValue) : null;

                if (!parsed || Number.isNaN(parsed.getTime())) {
                    return;
                }

                element.textContent = formatter.format(parsed);
            });
        });
    </script>
</body>
</html>
