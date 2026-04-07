<x-backoffice.layout pageTitle="Kelola Pesanan">
    <section class="space-y-5">
        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
            <h2 class="text-xl md:text-2xl font-extrabold text-[var(--rich-black)]">Daftar Pesanan</h2>

            <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total Order</p>
                    <p class="mt-1 text-xl font-extrabold text-[var(--rich-black)]">{{ (int) ($summary['total'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Menunggu</p>
                    <p class="mt-1 text-xl font-extrabold text-amber-800">{{ (int) ($summary['waiting'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Diproses</p>
                    <p class="mt-1 text-xl font-extrabold text-blue-800">{{ (int) ($summary['processing'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Selesai</p>
                    <p class="mt-1 text-xl font-extrabold text-emerald-800">{{ (int) ($summary['delivered'] ?? 0) }}</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 lg:grid-cols-12 gap-3">
                <div class="lg:col-span-8 space-y-3">
                    <input id="order-search" type="text" placeholder="Cari order ID / nama / email / meja..." class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[var(--rajah)]/70 focus:border-[var(--rajah)]">

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="order-status-tab inline-flex items-center rounded-xl border border-[#6A2B09] bg-[#6A2B09] text-[#FCB861] text-xs font-bold px-3 py-2 transition" data-status="all">Semua</button>
                        <button type="button" class="order-status-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-status="confirmed">Terkonfirmasi</button>
                        <button type="button" class="order-status-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-status="in_queue">Dalam Antrean</button>
                        <button type="button" class="order-status-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-status="in_progress">Sedang Diproses</button>
                        <button type="button" class="order-status-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-status="delivered">Disajikan</button>
                    </div>
                </div>

                <div class="lg:col-span-4">
                    <select id="order-sort" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[var(--rajah)]/70 focus:border-[var(--rajah)]">
                        <option value="default">Sort by: Terbaru</option>
                        <option value="queue-asc">No Antrian Terkecil</option>
                        <option value="queue-desc">No Antrian Terbesar</option>
                        <option value="total-asc">Total Termurah</option>
                        <option value="total-desc">Total Termahal</option>
                        <option value="table-asc">Nomor Meja Kecil</option>
                        <option value="table-desc">Nomor Meja Besar</option>
                    </select>
                </div>
            </div>
        </article>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left">
                    <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-bold">Order ID</th>
                            <th class="px-4 py-3 font-bold">Nama</th>
                            <th class="px-4 py-3 font-bold">Email</th>
                            <th class="px-4 py-3 font-bold">No Antrian</th>
                            <th class="px-4 py-3 font-bold">No Meja</th>
                            <th class="px-4 py-3 font-bold">Status</th>
                            <th class="px-4 py-3 font-bold">Total</th>
                            <th class="px-4 py-3 font-bold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="order-table-body" class="divide-y divide-slate-200">
                        @forelse (($orders ?? []) as $order)
                            @php
                                $status = strtoupper((string) ($order['status'] ?? 'UNKNOWN'));
                                $queueNumber = (int) ($order['queueNumber'] ?? 0);
                                $tableNumber = (int) ($order['tableNumber'] ?? 0);
                                $totalPrice = (float) ($order['totalPrice'] ?? 0);
                                $orderId = (string) ($order['orderId'] ?? '');
                                $displayId = 'ORD-' . strtoupper(substr($orderId, -6));

                                $customerName = trim((string) (data_get($order, 'customer.name') ?: data_get($order, 'customer.username') ?: '-'));
                                $customerEmail = trim((string) (data_get($order, 'customer.email') ?: '-'));

                                $statusLabel = match ($status) {
                                    'CONFIRMED' => 'Terkonfirmasi',
                                    'IN_QUEUE' => 'Dalam Antrean',
                                    'IN_PROGRESS' => 'Sedang Diproses',
                                    'DELIVERED' => 'Disajikan',
                                    default => ucfirst(strtolower(str_replace('_', ' ', $status))),
                                };

                                $statusClass = match ($status) {
                                    'CONFIRMED' => 'bg-amber-100 text-amber-700',
                                    'IN_QUEUE' => 'bg-orange-100 text-orange-700',
                                    'IN_PROGRESS' => 'bg-blue-100 text-blue-700',
                                    'DELIVERED' => 'bg-emerald-100 text-emerald-700',
                                    default => 'bg-slate-100 text-slate-700',
                                };
                            @endphp
                            <tr
                                class="order-row"
                                data-order-id="{{ strtolower($displayId) }}"
                                data-customer="{{ strtolower($customerName) }}"
                                data-email="{{ strtolower($customerEmail) }}"
                                data-status="{{ strtolower($status) }}"
                                data-total="{{ $totalPrice }}"
                                data-table="{{ $tableNumber }}"
                                data-queue="{{ $queueNumber }}"
                            >
                                <td class="px-4 py-3 text-sm font-extrabold text-[var(--rich-black)]">{{ $displayId }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $customerName !== '' ? $customerName : '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $customerEmail !== '' ? $customerEmail : '-' }}</td>
                                <td class="px-4 py-3 text-sm font-bold text-slate-700">#{{ $queueNumber }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $tableNumber > 0 ? $tableNumber : '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClass }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm font-extrabold text-[var(--philippine-bronze)]">Rp {{ number_format($totalPrice, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="/backoffice/daftar_pesanan/{{ urlencode($orderId) }}/status" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" class="min-w-40 rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-[var(--rajah)]/70 focus:border-[var(--rajah)]">
                                                @foreach (($statusOptions ?? []) as $statusOption)
                                                    @php
                                                        $optionLabel = match ($statusOption) {
                                                            'CONFIRMED' => 'Terkonfirmasi',
                                                            'IN_QUEUE' => 'Dalam Antrean',
                                                            'IN_PROGRESS' => 'Sedang Diproses',
                                                            'DELIVERED' => 'Disajikan',
                                                            default => $statusOption,
                                                        };
                                                    @endphp
                                                    <option value="{{ $statusOption }}" {{ $status === $statusOption ? 'selected' : '' }}>{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="inline-flex items-center rounded-lg bg-[var(--alloy-orange)] px-3 py-2 text-xs font-extrabold text-white transition hover:bg-[var(--philippine-bronze)]">Update</button>
                                        </form>

                                        <a href="/backoffice/daftar_pesanan?detail={{ urlencode($orderId) }}" class="inline-flex items-center rounded-lg border border-[#2563EB] bg-white hover:bg-blue-50 text-[#2563EB] text-xs font-extrabold px-3 py-2 transition">Lihat Detail</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">Belum ada data pesanan dengan pembayaran lunas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="order-filter-empty" class="hidden px-4 py-8 text-center border-t border-slate-200">
                <p class="text-sm font-semibold text-slate-500">Pesanan tidak ditemukan untuk filter ini.</p>
            </div>
        </section>
    </section>

    <script>
        (function () {
            const tableBody = document.getElementById('order-table-body');
            const searchInput = document.getElementById('order-search');
            const sortSelect = document.getElementById('order-sort');
            const tabs = Array.from(document.querySelectorAll('.order-status-tab'));
            const emptyState = document.getElementById('order-filter-empty');

            if (!tableBody) {
                return;
            }

            const rows = Array.from(tableBody.querySelectorAll('.order-row'));
            if (rows.length === 0) {
                return;
            }

            const baseOrder = new Map(rows.map((row, index) => [row, index]));
            let activeStatus = 'all';

            function normalize(text) {
                return String(text || '').toLowerCase().trim();
            }

            function sortRows(list) {
                const mode = sortSelect ? sortSelect.value : 'default';

                if (mode === 'default') {
                    return list.sort((a, b) => baseOrder.get(a) - baseOrder.get(b));
                }

                if (mode === 'queue-asc') {
                    return list.sort((a, b) => Number(a.dataset.queue || 0) - Number(b.dataset.queue || 0));
                }

                if (mode === 'queue-desc') {
                    return list.sort((a, b) => Number(b.dataset.queue || 0) - Number(a.dataset.queue || 0));
                }

                if (mode === 'total-asc') {
                    return list.sort((a, b) => Number(a.dataset.total || 0) - Number(b.dataset.total || 0));
                }

                if (mode === 'total-desc') {
                    return list.sort((a, b) => Number(b.dataset.total || 0) - Number(a.dataset.total || 0));
                }

                if (mode === 'table-asc') {
                    return list.sort((a, b) => Number(a.dataset.table || 0) - Number(b.dataset.table || 0));
                }

                if (mode === 'table-desc') {
                    return list.sort((a, b) => Number(b.dataset.table || 0) - Number(a.dataset.table || 0));
                }

                return list;
            }

            function applyFilters() {
                const keyword = normalize(searchInput ? searchInput.value : '');

                let visible = rows.filter(function (row) {
                    const orderId = normalize(row.dataset.orderId);
                    const customer = normalize(row.dataset.customer);
                    const email = normalize(row.dataset.email);
                    const table = normalize(row.dataset.table);
                    const status = normalize(row.dataset.status);

                    const bySearch = keyword === '' || orderId.includes(keyword) || customer.includes(keyword) || email.includes(keyword) || table.includes(keyword);
                    const byStatus = activeStatus === 'all' || status === activeStatus;

                    return bySearch && byStatus;
                });

                visible = sortRows(visible);

                rows.forEach(function (row) {
                    row.classList.add('hidden');
                });

                visible.forEach(function (row) {
                    row.classList.remove('hidden');
                    tableBody.appendChild(row);
                });

                if (emptyState) {
                    emptyState.classList.toggle('hidden', visible.length !== 0);
                }
            }

            if (searchInput) {
                searchInput.addEventListener('input', applyFilters);
            }

            if (sortSelect) {
                sortSelect.addEventListener('change', applyFilters);
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    activeStatus = normalize(tab.dataset.status || 'all') || 'all';

                    tabs.forEach(function (btn) {
                        const selected = btn === tab;
                        btn.classList.toggle('border-[#6A2B09]', selected);
                        btn.classList.toggle('bg-[#6A2B09]', selected);
                        btn.classList.toggle('text-[#FCB861]', selected);
                        btn.classList.toggle('border-slate-300', !selected);
                        btn.classList.toggle('bg-white', !selected);
                        btn.classList.toggle('text-slate-700', !selected);
                    });

                    applyFilters();
                });
            });

            applyFilters();
        })();
    </script>

    @include('backoffice.order.detail.detail', ['selectedOrder' => $selectedOrder])
</x-backoffice.layout>
