@if (!empty($selectedOrder))
    @php
        $detailStatus = strtoupper((string) ($selectedOrder['status'] ?? 'UNKNOWN'));
        $detailPaymentStatus = strtoupper((string) ($selectedOrder['paymentStatus'] ?? 'PENDING'));
        $detailOrderId = (string) ($selectedOrder['orderId'] ?? '');
        $detailDisplayId = 'ORD-' . strtoupper(substr($detailOrderId, -6));

        $detailStatusLabel = match ($detailStatus) {
            'PENDING_PAYMENT' => 'Menunggu Pembayaran',
            'PAYMENT_FAILED' => 'Pembayaran Gagal',
            'CONFIRMED' => 'Terkonfirmasi',
            'IN_QUEUE' => 'Dalam Antrean',
            'IN_PROGRESS' => 'Sedang Diproses',
            'DELIVERED' => 'Disajikan',
            default => ucfirst(strtolower(str_replace('_', ' ', $detailStatus))),
        };

        $detailStatusClass = match ($detailStatus) {
            'PENDING_PAYMENT' => 'bg-slate-100 text-slate-700',
            'PAYMENT_FAILED' => 'bg-rose-100 text-rose-700',
            'CONFIRMED' => 'bg-amber-100 text-amber-700',
            'IN_QUEUE' => 'bg-orange-100 text-orange-700',
            'IN_PROGRESS' => 'bg-blue-100 text-blue-700',
            'DELIVERED' => 'bg-emerald-100 text-emerald-700',
            default => 'bg-slate-100 text-slate-700',
        };

        $detailPaymentLabel = match ($detailPaymentStatus) {
            'PAID', 'SUCCESS', 'SETTLEMENT' => 'Lunas',
            'FAILED', 'DENY' => 'Gagal',
            'CANCELED', 'CANCEL' => 'Dibatalkan',
            'EXPIRED', 'EXPIRE' => 'Kedaluwarsa',
            default => 'Menunggu',
        };

        $detailPaymentClass = match ($detailPaymentLabel) {
            'Lunas' => 'bg-emerald-100 text-emerald-700',
            'Gagal', 'Dibatalkan', 'Kedaluwarsa' => 'bg-rose-100 text-rose-700',
            default => 'bg-amber-100 text-amber-700',
        };

        $detailCustomerName = trim((string) (data_get($selectedOrder, 'customer.name') ?: data_get($selectedOrder, 'customer.username') ?: '-'));
        $detailCustomerEmail = trim((string) (data_get($selectedOrder, 'customer.email') ?: '-'));
        $detailPaidAtRaw = (string) ($selectedOrder['paidAt'] ?? '');
        $detailPaidAtLabel = '-';

        if ($detailPaidAtRaw !== '') {
            try {
                $detailPaidAtLabel = \Carbon\Carbon::parse($detailPaidAtRaw)->format('d M Y, H:i:s');
            } catch (\Throwable $exception) {
                $detailPaidAtLabel = $detailPaidAtRaw;
            }
        }
    @endphp

    <div class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm"></div>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-extrabold text-[var(--rich-black)]">Detail Pesanan</h3>
                    <p class="text-sm font-semibold text-slate-500">{{ $detailDisplayId }}</p>
                </div>
                <a href="/backoffice/daftar_pesanan" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-bold px-3 py-1.5 transition">Tutup</a>
            </div>

            <div class="p-5 space-y-4 max-h-[72vh] overflow-y-auto">
                <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left">
                            <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 font-bold">Informasi</th>
                                    <th class="px-4 py-3 font-bold">Nilai</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-sm">
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-600">Nama Pemesan</td>
                                    <td class="px-4 py-3 text-slate-800">{{ $detailCustomerName !== '' ? $detailCustomerName : '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-600">Email</td>
                                    <td class="px-4 py-3 text-slate-800">{{ $detailCustomerEmail !== '' ? $detailCustomerEmail : '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-600">Nomor Antrian</td>
                                    <td class="px-4 py-3 text-slate-800">#{{ (int) ($selectedOrder['queueNumber'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-600">Nomor Meja</td>
                                    <td class="px-4 py-3 text-slate-800">{{ (int) ($selectedOrder['tableNumber'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-600">Status Pesanan</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $detailStatusClass }}">{{ $detailStatusLabel }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-600">Status Pembayaran</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $detailPaymentClass }}">{{ $detailPaymentLabel }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-600">Waktu Pemesanan</td>
                                    <td class="px-4 py-3 text-slate-800">{{ $detailPaidAtLabel }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-600">Total Pesanan</td>
                                    <td class="px-4 py-3 text-[var(--philippine-bronze)] font-extrabold">Rp {{ number_format((float) ($selectedOrder['totalPrice'] ?? 0), 0, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                        <h4 class="text-sm font-extrabold text-slate-700">Rincian Menu</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left">
                            <thead class="bg-white border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 font-bold">No</th>
                                    <th class="px-4 py-3 font-bold">Nama Menu</th>
                                    <th class="px-4 py-3 font-bold">Qty</th>
                                    <th class="px-4 py-3 font-bold">Harga</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                                @forelse (($selectedOrder['items'] ?? []) as $index => $item)
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-500">{{ $index + 1 }}</td>
                                        <td class="px-4 py-3 font-semibold text-slate-800">{{ $item['name'] ?? '-' }}</td>
                                        <td class="px-4 py-3">{{ (int) ($item['quantity'] ?? 0) }}</td>
                                        <td class="px-4 py-3">Rp {{ number_format((float) ($item['price'] ?? 0), 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-sm font-semibold text-slate-500">Tidak ada item.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endif
