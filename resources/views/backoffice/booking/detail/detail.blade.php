@if (!empty($selectedBooking))
    @php
        $detailStatus = strtoupper((string) ($selectedBooking['status'] ?? 'UNKNOWN'));
        $detailStatusLabel = match ($detailStatus) {
            'CONFIRMED' => 'Terkonfirmasi',
            'SEATED' => 'Sedang Diproses',
            'COMPLETED' => 'Disajikan',
            'NO_SHOW' => 'Tidak Hadir',
            'IN_QUEUE' => 'Dalam Antrean',
            'IN_PROGRESS' => 'Sedang Diproses',
            'DELIVERED' => 'Disajikan',
            default => ucfirst(strtolower(str_replace('_', ' ', $detailStatus))),
        };
        $detailStatusClass = match ($detailStatus) {
            'CONFIRMED' => 'bg-amber-100 text-amber-700',
            'SEATED' => 'bg-blue-100 text-blue-700',
            'COMPLETED' => 'bg-emerald-100 text-emerald-700',
            'NO_SHOW' => 'bg-rose-100 text-rose-700',
            'IN_QUEUE' => 'bg-orange-100 text-orange-700',
            'IN_PROGRESS' => 'bg-blue-100 text-blue-700',
            'DELIVERED' => 'bg-emerald-100 text-emerald-700',
            default => 'bg-slate-100 text-slate-700',
        };

        $detailStart = '-';
        $detailEnd = '-';
        try {
            $detailStart = \Illuminate\Support\Carbon::parse((string) ($selectedBooking['bookingStartAt'] ?? ''))
                ->timezone('Asia/Jakarta')
                ->format('d M Y H:i');
            $detailEnd = \Illuminate\Support\Carbon::parse((string) ($selectedBooking['bookingEndAt'] ?? ''))
                ->timezone('Asia/Jakarta')
                ->format('d M Y H:i');
        } catch (\Throwable $exception) {
            $detailStart = '-';
            $detailEnd = '-';
        }
    @endphp

    <div data-modal-root class="bo-modal-root fixed inset-0 z-[70]">
        <div data-modal-overlay class="bo-modal-backdrop fixed inset-0 bg-black/40 backdrop-blur-sm"></div>
        <div class="bo-modal-wrap fixed inset-0 flex items-center justify-center p-4">
            <div class="bo-modal-panel w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-extrabold text-[var(--rich-black)]">Detail Booking</h3>
                        <p class="text-sm font-semibold text-slate-500">{{ (string) ($selectedBooking['displayId'] ?? '-') }}</p>
                    </div>
                    <button type="button" data-modal-close class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-bold px-3 py-1.5 transition">Tutup</button>
                </div>

                <div class="p-5 space-y-4">
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
                                        <td class="px-4 py-3 text-slate-800">{{ (string) ($selectedBooking['customerName'] ?? '-') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-600">Nomor Meja</td>
                                        <td class="px-4 py-3 text-slate-800">Meja {{ (int) ($selectedBooking['tableNumber'] ?? 0) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-600">Mulai</td>
                                        <td class="px-4 py-3 text-slate-800">{{ $detailStart }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-600">Selesai</td>
                                        <td class="px-4 py-3 text-slate-800">{{ $detailEnd }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-600">Durasi</td>
                                        <td class="px-4 py-3 text-slate-800">{{ (int) ($selectedBooking['durationHours'] ?? 0) }} jam</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-600">Charge</td>
                                        <td class="px-4 py-3 text-[var(--philippine-bronze)] font-extrabold">Rp {{ number_format((int) ($selectedBooking['extraCharge'] ?? 0), 0, ',', '.') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-600">Status</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $detailStatusClass }}">{{ $detailStatusLabel }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                            <h4 class="text-sm font-extrabold text-slate-700">Rincian Pesanan</h4>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[640px] text-left">
                                <thead class="bg-white border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3 font-bold">No</th>
                                        <th class="px-4 py-3 font-bold">Nama Menu</th>
                                        <th class="px-4 py-3 font-bold">Qty</th>
                                        <th class="px-4 py-3 font-bold">Harga</th>
                                        <th class="px-4 py-3 font-bold">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                                    @forelse (($selectedBooking['items'] ?? []) as $index => $item)
                                        @php
                                            $qty = (int) ($item['quantity'] ?? 0);
                                            $price = (int) ($item['price'] ?? 0);
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-3 font-semibold text-slate-500">{{ $index + 1 }}</td>
                                            <td class="px-4 py-3 font-semibold text-slate-800">{{ (string) ($item['name'] ?? '-') }}</td>
                                            <td class="px-4 py-3">{{ $qty }}</td>
                                            <td class="px-4 py-3">Rp {{ number_format($price, 0, ',', '.') }}</td>
                                            <td class="px-4 py-3">Rp {{ number_format($qty * $price, 0, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-8 text-center text-sm font-semibold text-slate-500">Belum ada item pesanan.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
@endif
