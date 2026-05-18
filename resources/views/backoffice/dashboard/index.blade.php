<x-backoffice.layout pageTitle="Dashboard" pageSubtitle="Halo Admin!, Selamat Datang Kembali">
    <section class="grid grid-cols-1 xl:grid-cols-12 gap-5">
        <div class="xl:col-span-8 space-y-5">
            <article id="overview-panel" class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-lg md:text-xl font-extrabold text-[var(--rich-black)]">Pesanan Belum Diproses</h2>
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                        {{ count($activeOrders ?? []) }} Pesanan
                    </span>
                </div>

                <div class="space-y-3">
                    @forelse ($activeOrders as $order)
                        <div class="rounded-xl border border-slate-200 hover:border-[var(--alloy-orange)] hover:shadow-md transition p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3 pb-3 border-b border-slate-100">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500">Order ID</p>
                                    <p class="text-sm font-extrabold text-[var(--rich-black)]">{{ $order['display_id'] }}</p>
                                    <p class="text-sm font-semibold text-slate-700 mt-1">{{ $order['customer_name'] ?? '-' }}</p>
                                    <p class="text-xs text-slate-500 mt-1">{{ (int) ($order['item_count'] ?? 0) }} item</p>
                                </div>
                                @php
                                    $status = strtoupper($order['status']);
                                    $statusMap = [
                                        'CONFIRMED' => ['Terkonfirmasi', 'bg-amber-100 text-amber-700'],
                                    ];
                                    $statusMeta = $statusMap[$status] ?? [ucwords(strtolower(str_replace('_', ' ', $status))), 'bg-slate-100 text-slate-700'];
                                @endphp
                                <span class="text-xs font-bold px-2.5 py-1 rounded-full {{ $statusMeta[1] }}">{{ $statusMeta[0] }}</span>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <form method="POST" action="/backoffice/daftar_pesanan/{{ urlencode((string) ($order['id'] ?? '')) }}/status">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="IN_QUEUE">
                                    <button type="submit" class="inline-flex items-center rounded-lg bg-[var(--alloy-orange)] hover:bg-[var(--philippine-bronze)] text-white text-sm font-bold px-3.5 py-2 transition">
                                        Masukkan Antrean
                                    </button>
                                </form>
                                <a href="/backoffice/dashboard?detail={{ urlencode((string) ($order['id'] ?? '')) }}" data-modal-link class="inline-flex items-center rounded-lg border border-[#2563EB] bg-white hover:bg-blue-50 text-[#2563EB] text-sm font-bold px-3.5 py-2 transition">
                                    Lihat Detail
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm font-semibold text-slate-500">
                            Tidak ada pesanan terkonfirmasi yang menunggu diproses.
                        </div>
                    @endforelse
                </div>
            </article>
        </div>

        <div id="user-panel" class="xl:col-span-4 space-y-5">
            <article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
                <h2 class="text-lg font-extrabold text-[var(--rich-black)] mb-4">Aksi Cepat</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 xl:grid-cols-1 gap-3">
                    <a href="/backoffice/daftar_menu" class="text-center rounded-xl bg-[var(--alloy-orange)] hover:bg-[var(--philippine-bronze)] text-white font-bold py-3 transition shadow-sm">Kelola Menu</a>
                    <a href="/backoffice/daftar_pesanan" class="text-center rounded-xl bg-[var(--rajah)] hover:brightness-95 text-[var(--philippine-bronze)] font-bold py-3 transition shadow-sm">Lihat Pesanan</a>
                    <a href="/backoffice/daftar_meja" class="text-center rounded-xl bg-[var(--auro-metal-saurus)] hover:brightness-95 text-white font-bold py-3 transition shadow-sm">Kelola Meja</a>
                </div>
            </article>

            <article id="payment-panel" class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
                <h2 class="text-lg font-extrabold text-[var(--rich-black)] mb-4">Notifikasi</h2>
                <div class="space-y-2.5">
                    <div class="rounded-xl border border-yellow-200 bg-yellow-50 px-3.5 py-3 text-sm text-yellow-800 font-semibold">{{ $notifications['new_paid_orders'] }} Pesanan Baru Masuk (Pembayaran Sukses)</div>
                    <div class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-800 font-semibold">{{ $notifications['out_of_stock_menus'] }} Menu Stok Habis</div>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
                <h2 class="text-lg font-extrabold text-[var(--rich-black)] mb-4">Aktivitas Terbaru</h2>
                <ul class="space-y-2.5 text-sm text-slate-700">
                    @foreach ($recentActivities as $activity)
                        <li class="rounded-lg bg-slate-50 px-3 py-2">{{ $activity }}</li>
                    @endforeach
                </ul>
            </article>
        </div>
    </section>

    @if (!empty($selectedOrder))
        <div data-modal-root class="bo-modal-root fixed inset-0 z-[70]">
            <div data-modal-overlay class="bo-modal-backdrop fixed inset-0 bg-black/40 backdrop-blur-sm"></div>
            <div class="bo-modal-wrap fixed inset-0 flex items-center justify-center p-4">
                <div class="bo-modal-panel w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-extrabold text-[var(--rich-black)]">Detail Pesanan</h3>
                        <p class="text-sm font-semibold text-slate-500">{{ $selectedOrder['display_id'] ?? '-' }}</p>
                    </div>
                    <button type="button" data-modal-close class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-bold px-3 py-1.5 transition">Tutup</button>
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
                                        <td class="px-4 py-3 text-slate-800">{{ $selectedOrder['customer_name'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-600">Email</td>
                                        <td class="px-4 py-3 text-slate-800">{{ $selectedOrder['customer_email'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-600">Nomor Antrian</td>
                                        <td class="px-4 py-3 text-slate-800">#{{ (int) ($selectedOrder['queue_number'] ?? 0) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-600">Nomor Meja</td>
                                        <td class="px-4 py-3 text-slate-800">{{ (int) ($selectedOrder['table_number'] ?? 0) }}</td>
                                    </tr>
                                    @php
                                        $dashOrderType = strtoupper((string) ($selectedOrder['order_type'] ?? 'DINE_IN'));
                                        $dashOrderTypeLabel = match ($dashOrderType) {
                                            'DINE_IN' => 'Dine In',
                                            'TAKE_AWAY' => 'Take Away',
                                            default => ucwords(strtolower(str_replace('_', ' ', $dashOrderType))),
                                        };
                                        $dashOrderTypeClass = match ($dashOrderType) {
                                            'DINE_IN' => 'bg-sky-100 text-sky-700',
                                            'TAKE_AWAY' => 'bg-violet-100 text-violet-700',
                                            default => 'bg-slate-100 text-slate-700',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-600">Tipe Pesanan</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $dashOrderTypeClass }}">{{ $dashOrderTypeLabel }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-600">Status Pesanan</td>
                                        <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold bg-amber-100 text-amber-700">Terkonfirmasi</span></td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-600">Total Pesanan</td>
                                        <td class="px-4 py-3 text-[var(--philippine-bronze)] font-extrabold">Rp {{ number_format((float) ($selectedOrder['total_price'] ?? 0), 0, ',', '.') }}</td>
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
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                                    @forelse (($selectedOrder['items'] ?? []) as $index => $item)
                                        <tr>
                                            <td class="px-4 py-3 font-semibold text-slate-500">{{ $index + 1 }}</td>
                                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $item['name'] ?? '-' }}</td>
                                            <td class="px-4 py-3">{{ (int) ($item['quantity'] ?? 0) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-4 py-8 text-center text-sm font-semibold text-slate-500">Tidak ada item.</td>
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
</x-backoffice.layout>
