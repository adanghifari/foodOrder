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
                        <button type="button" class="order-status-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-status="CONFIRMED">Terkonfirmasi</button>
                        <button type="button" class="order-status-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-status="IN_QUEUE">Dalam Antrean</button>
                        <button type="button" class="order-status-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-status="IN_PROGRESS">Sedang Diproses</button>
                        <button type="button" class="order-status-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-status="DELIVERED">Disajikan</button>
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

        <section id="order-grid" class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            @forelse (($orders ?? []) as $order)
                @php
                    $status = (string) ($order['status'] ?? 'UNKNOWN');
                    $paymentStatus = (string) ($order['paymentStatus'] ?? 'PENDING');
                    $queueNumber = (int) ($order['queueNumber'] ?? 0);
                    $tableNumber = (int) ($order['tableNumber'] ?? 0);
                    $totalPrice = (float) ($order['totalPrice'] ?? 0);
                    $customerName = trim((string) data_get($order, 'customer.name', '-'));
                    $customerEmail = trim((string) (data_get($order, 'customer.email') ?: data_get($order, 'customer.username', '-')));
                    $orderId = (string) ($order['orderId'] ?? '');
                    $displayId = 'ORD-' . strtoupper(substr((string) ($order['orderId'] ?? ''), -6));

                    $statusClass = match ($status) {
                        'CONFIRMED' => 'bg-amber-100 text-amber-700',
                        'IN_QUEUE' => 'bg-orange-100 text-orange-700',
                        'IN_PROGRESS' => 'bg-blue-100 text-blue-700',
                        'DELIVERED' => 'bg-emerald-100 text-emerald-700',
                        default => 'bg-slate-100 text-slate-700',
                    };

                    $statusLabel = $status === 'DELIVERED' ? 'Disajikan' : str_replace('_', ' ', $status);

                    $paymentClass = in_array($paymentStatus, ['PAID', 'SUCCESS'], true)
                        ? 'bg-emerald-100 text-emerald-700'
                        : 'bg-slate-100 text-slate-700';
                @endphp

                <article
                    class="order-card rounded-2xl border border-slate-200 bg-white shadow-sm p-4 md:p-5"
                    data-order-id="{{ strtolower($displayId) }}"
                    data-customer="{{ strtolower($customerName) }}"
                    data-email="{{ strtolower($customerEmail) }}"
                    data-table="{{ $tableNumber }}"
                    data-status="{{ strtolower($status) }}"
                    data-queue="{{ $queueNumber }}"
                    data-total="{{ $totalPrice }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-extrabold text-[var(--rich-black)]">{{ $displayId }}</h3>
                            <p class="text-xs text-slate-500">Customer: <span class="font-semibold text-slate-700">{{ $customerName !== '' ? $customerName : '-' }}</span></p>
                            <p class="text-xs text-slate-500 mt-0.5">Email: <span class="font-semibold text-slate-700">{{ $customerEmail !== '' ? $customerEmail : '-' }}</span></p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-500">Antrian</p>
                            <p class="text-lg font-extrabold text-[var(--philippine-bronze)]">#{{ $queueNumber }}</p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClass }}">{{ $statusLabel }}</span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $paymentClass }}">{{ $paymentStatus }}</span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold bg-slate-100 text-slate-700">Meja {{ $tableNumber > 0 ? $tableNumber : '-' }}</span>
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
                        <form method="POST" action="/backoffice/daftar_pesanan/{{ urlencode($orderId) }}/status" class="flex items-center gap-2 w-full sm:w-auto">
                            @csrf
                            @method('PATCH')
                            <select name="status" class="w-full sm:w-auto min-w-44 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[var(--rajah)]/70 focus:border-[var(--rajah)]">
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
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[var(--alloy-orange)] hover:bg-[var(--philippine-bronze)] text-white text-xs font-extrabold px-3 py-2 transition">Update</button>
                        </form>

                        <a href="/backoffice/daftar_pesanan?detail={{ urlencode($orderId) }}" class="inline-flex items-center justify-center rounded-lg border border-[#2563EB] bg-white hover:bg-blue-50 text-[#2563EB] text-xs font-extrabold px-3 py-2 transition sm:ml-auto">Lihat Detail Pesanan</a>
                    </div>

                    <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Items</p>
                        <ul class="mt-2 space-y-1.5">
                            @forelse (($order['items'] ?? []) as $item)
                                <li class="flex items-center justify-between text-sm text-slate-700 gap-2">
                                    <span class="font-semibold">{{ $item['name'] ?? '-' }} <span class="text-slate-500">x{{ (int) ($item['quantity'] ?? 0) }}</span></span>
                                    <span class="text-slate-600">Rp {{ number_format((float) ($item['price'] ?? 0), 0, ',', '.') }}</span>
                                </li>
                            @empty
                                <li class="text-sm text-slate-500">Tidak ada item.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-200 flex items-center justify-between gap-3">
                        <p class="text-sm text-slate-500">Total</p>
                        <p class="text-base font-extrabold text-[var(--philippine-bronze)]">Rp {{ number_format($totalPrice, 0, ',', '.') }}</p>
                    </div>
                </article>
            @empty
                <article class="xl:col-span-2 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <p class="text-sm font-semibold text-slate-500">Belum ada data pesanan.</p>
                </article>
            @endforelse

            <article id="order-filter-empty" class="hidden xl:col-span-2 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                <p class="text-sm font-semibold text-slate-500">Pesanan tidak ditemukan untuk filter ini.</p>
            </article>
        </section>
    </section>

    <script>
        (function () {
            const grid = document.getElementById('order-grid');
            const searchInput = document.getElementById('order-search');
            const sortSelect = document.getElementById('order-sort');
            const tabs = Array.from(document.querySelectorAll('.order-status-tab'));
            const emptyState = document.getElementById('order-filter-empty');

            if (!grid) {
                return;
            }

            const cards = Array.from(grid.querySelectorAll('.order-card'));
            if (cards.length === 0) {
                return;
            }

            const baseOrder = new Map(cards.map((card, index) => [card, index]));
            let activeStatus = 'all';

            function normalize(text) {
                return String(text || '').toLowerCase().trim();
            }

            function sortCards(list) {
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

                let visible = cards.filter(function (card) {
                    const orderId = normalize(card.dataset.orderId);
                    const customer = normalize(card.dataset.customer);
                    const email = normalize(card.dataset.email);
                    const table = normalize(card.dataset.table);
                    const status = normalize(card.dataset.status);

                    const bySearch = keyword === '' || orderId.includes(keyword) || customer.includes(keyword) || email.includes(keyword) || table.includes(keyword);
                    const byStatus = activeStatus === 'all' || status === activeStatus;

                    return bySearch && byStatus;
                });

                visible = sortCards(visible);

                cards.forEach(function (card) {
                    card.classList.add('hidden');
                });

                visible.forEach(function (card) {
                    card.classList.remove('hidden');
                    grid.appendChild(card);
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

    @if (!empty($selectedOrder))
        @php
            $detailStatus = (string) ($selectedOrder['status'] ?? 'UNKNOWN');
            $detailStatusLabel = match ($detailStatus) {
                'CONFIRMED' => 'Terkonfirmasi',
                'IN_QUEUE' => 'Dalam Antrean',
                'IN_PROGRESS' => 'Sedang Diproses',
                'DELIVERED' => 'Disajikan',
                default => str_replace('_', ' ', $detailStatus),
            };
            $detailOrderId = (string) ($selectedOrder['orderId'] ?? '');
            $detailDisplayId = 'ORD-' . strtoupper(substr($detailOrderId, -6));
            $detailCustomerName = trim((string) data_get($selectedOrder, 'customer.name', '-'));
            $detailCustomerEmail = trim((string) (data_get($selectedOrder, 'customer.email') ?: data_get($selectedOrder, 'customer.username', '-')));
        @endphp

        <div class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm"></div>
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-extrabold text-[var(--rich-black)]">Detail Pesanan</h3>
                        <p class="text-sm font-semibold text-slate-500">{{ $detailDisplayId }}</p>
                    </div>
                    <a href="/backoffice/daftar_pesanan" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-bold px-3 py-1.5 transition">Tutup</a>
                </div>

                <div class="p-5 space-y-4 max-h-[72vh] overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Nama Pemesan</p>
                            <p class="mt-1 text-sm font-semibold text-slate-700">{{ $detailCustomerName !== '' ? $detailCustomerName : '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Email</p>
                            <p class="mt-1 text-sm font-semibold text-slate-700">{{ $detailCustomerEmail !== '' ? $detailCustomerEmail : '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Status</p>
                            <p class="mt-1 text-sm font-semibold text-slate-700">{{ $detailStatusLabel }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Meja</p>
                            <p class="mt-1 text-sm font-semibold text-slate-700">{{ (int) ($selectedOrder['tableNumber'] ?? 0) }}</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Detail Item</p>
                        <ul class="mt-2 space-y-2">
                            @forelse (($selectedOrder['items'] ?? []) as $item)
                                <li class="flex items-center justify-between text-sm text-slate-700 gap-3">
                                    <span class="font-semibold">{{ $item['name'] ?? '-' }} x{{ (int) ($item['quantity'] ?? 0) }}</span>
                                    <span>Rp {{ number_format((float) ($item['price'] ?? 0), 0, ',', '.') }}</span>
                                </li>
                            @empty
                                <li class="text-sm text-slate-500">Tidak ada item.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-sm text-slate-500">Total Pembayaran</p>
                        <p class="text-base font-extrabold text-[var(--philippine-bronze)]">Rp {{ number_format((float) ($selectedOrder['totalPrice'] ?? 0), 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-backoffice.layout>
