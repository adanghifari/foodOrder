<x-backoffice.layout pageTitle="Kelola Meja">
    <section class="space-y-5">
        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-xl md:text-2xl font-extrabold text-[var(--rich-black)]">Kelola Meja</h2>
                </div>
                <a href="/backoffice/daftar_pesanan" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-bold px-3.5 py-2 transition">Lihat Kelola Pesanan</a>
            </div>

            <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total Meja</p>
                    <p class="mt-1 text-xl font-extrabold text-[var(--rich-black)]">{{ (int) ($tableStats['total'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-red-200 bg-red-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-red-700">Meja Terisi</p>
                    <p class="mt-1 text-xl font-extrabold text-red-800">{{ (int) ($tableStats['occupied'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Meja Tersedia</p>
                    <p class="mt-1 text-xl font-extrabold text-emerald-800">{{ (int) ($tableStats['available'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Order Aktif</p>
                    <p class="mt-1 text-xl font-extrabold text-blue-800">{{ (int) ($tableStats['activeOrders'] ?? 0) }}</p>
                </div>
            </div>

            <form method="POST" action="/backoffice/kelola_meja/assign" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                @csrf
                @method('PATCH')

                <div class="grid grid-cols-1 xl:grid-cols-12 gap-3">
                    <div class="xl:col-span-6">
                        <label for="order_id" class="block text-xs font-bold uppercase tracking-wide text-slate-600">Pilih Order Aktif</label>
                        <select id="order_id" name="order_id" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[var(--rajah)]/70 focus:border-[var(--rajah)]" required>
                            <option value="">-- Pilih order --</option>
                            @foreach (($assignableOrders ?? []) as $order)
                                @php
                                    $selectedOrderId = (string) old('order_id');
                                    $isSelected = $selectedOrderId !== '' && $selectedOrderId === (string) ($order['orderId'] ?? '');
                                @endphp
                                <option value="{{ $order['orderId'] ?? '' }}" {{ $isSelected ? 'selected' : '' }}>
                                    {{ $order['displayId'] ?? '-' }} | {{ $order['customerName'] ?? '-' }} | Meja {{ (int) ($order['tableNumber'] ?? 0) }} | #{{ (int) ($order['queueNumber'] ?? 0) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="xl:col-span-4">
                        <label for="table_number" class="block text-xs font-bold uppercase tracking-wide text-slate-600">Pindahkan ke Meja</label>
                        <select id="table_number" name="table_number" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[var(--rajah)]/70 focus:border-[var(--rajah)]" required>
                            <option value="">-- Pilih meja --</option>
                            @foreach (($availableTables ?? []) as $table)
                                @php
                                    $tableId = (int) ($table['tableId'] ?? 0);
                                    $selectedTable = (int) old('table_number');
                                    $isSelectedTable = $selectedTable > 0 && $selectedTable === $tableId;
                                @endphp
                                <option value="{{ $tableId }}" {{ $isSelectedTable ? 'selected' : '' }}>
                                    Meja {{ $tableId }} (Tersedia)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="xl:col-span-2 xl:self-end">
                        <button type="submit" class="w-full inline-flex items-center justify-center rounded-lg bg-[var(--alloy-orange)] hover:bg-[var(--philippine-bronze)] text-white text-sm font-extrabold px-4 py-2.5 transition">
                            Assign Meja
                        </button>
                    </div>
                </div>
            </form>
        </article>

        <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            @forelse (($tables ?? []) as $table)
                @php
                    $tableId = (int) ($table['tableId'] ?? 0);
                    $isOccupied = (bool) ($table['isOccupied'] ?? false);
                    $activeOrderCount = (int) ($table['activeOrderCount'] ?? 0);
                    $currentOrder = $table['currentOrder'] ?? null;
                    $occupyingOrders = collect($table['occupyingOrders'] ?? []);
                    $todaySectionItems = collect($table['todaySectionItems'] ?? []);
                    $upcomingSectionItems = collect($table['upcomingSectionItems'] ?? []);
                    $canClearNow = (bool) ($table['canClearNow'] ?? false);
                    $cardClass = $isOccupied
                        ? 'border-red-200 bg-red-50'
                        : 'border-emerald-200 bg-emerald-50';
                @endphp

                <article class="rounded-2xl border shadow-sm p-4 {{ $cardClass }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-extrabold text-[var(--rich-black)]">Meja {{ $tableId }}</h3>
                            <p class="text-xs font-bold uppercase tracking-wide {{ $isOccupied ? 'text-red-700' : 'text-emerald-700' }}">
                                {{ $isOccupied ? 'Terisi' : 'Tersedia' }}
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $isOccupied ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                            {{ $activeOrderCount }} order
                        </span>
                    </div>

                    <button
                        type="button"
                        class="mt-3 w-full inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-extrabold px-3 py-2 transition"
                        data-table-detail-open="{{ $tableId }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <span>Lihat Detail Meja</span>
                    </button>

                    @if ($currentOrder)
                        @php
                            $currentOrderStatus = strtoupper((string) ($currentOrder['status'] ?? 'UNKNOWN'));
                            $currentOrderStatusLabel = match ($currentOrderStatus) {
                                'PENDING_PAYMENT' => 'Menunggu Pembayaran',
                                'CONFIRMED' => 'Terkonfirmasi',
                                'IN_QUEUE' => 'Dalam Antrean',
                                'IN_PROGRESS' => 'Sedang Diproses',
                                'DELIVERED' => 'Disajikan',
                                'CANCELED', 'CANCEL' => 'Dibatalkan',
                                default => ucfirst(strtolower(str_replace('_', ' ', $currentOrderStatus))),
                            };
                        @endphp
                        <div class="mt-3 rounded-xl border border-red-200 bg-white/80 p-3 text-sm text-slate-700 space-y-1">
                            <p class="font-bold text-[var(--rich-black)]">{{ $currentOrder['displayId'] ?? '-' }}</p>
                            <p>Customer: {{ $currentOrder['customerName'] ?? '-' }}</p>
                            <p>Email: {{ $currentOrder['customerEmail'] ?? '-' }}</p>
                            @if (!empty($currentOrder['bookingTimeRange']))
                                <p>Jam Booking: {{ $currentOrder['bookingTimeRange'] }}</p>
                            @endif
                            <p>Status: {{ $currentOrderStatusLabel }}</p>
                        </div>

                    @else
                        <div class="mt-3 rounded-xl border border-emerald-200 bg-white/80 p-3 text-sm font-semibold text-emerald-800">
                            Belum ada order aktif di meja ini.
                        </div>
                    @endif

                    @if ($isOccupied)
                        <form
                            method="POST"
                            action="/backoffice/kelola_meja/{{ $tableId }}/clear"
                            class="mt-3"
                            @if (!$canClearNow)
                                data-clear-guard="true"
                                data-clear-guard-message="Pesanan belum diserahkan. Meja hanya bisa dikosongkan jika semua order aktif sudah berstatus Disajikan."
                            @endif
                            data-notify-confirm
                            data-confirm-type="warning"
                            data-confirm-badge="Kosongkan Meja"
                            data-confirm-title="Kosongkan meja {{ $tableId }}?"
                            data-confirm-message="Semua order aktif di meja ini akan ditandai selesai agar meja bisa dipakai lagi."
                            data-confirm-button="Ya, kosongkan"
                            data-cancel-button="Batal"
                        >
                            @csrf
                            @method('PATCH')
                            <button
                                type="submit"
                                class="w-full inline-flex items-center justify-center rounded-lg border border-red-700 bg-red-700 hover:bg-red-800 text-white text-xs font-extrabold px-3 py-2 transition"
                            >
                                Kosongkan Meja
                            </button>
                        </form>
                    @endif
                </article>

                <div
                    class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4"
                    data-table-detail-modal="{{ $tableId }}"
                >
                    <div class="w-full max-w-3xl rounded-2xl bg-white shadow-xl">
                        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                            <h4 class="text-lg font-extrabold text-[var(--rich-black)]">Detail Meja {{ $tableId }}</h4>
                            <button type="button" class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100" data-table-detail-close="{{ $tableId }}">Tutup</button>
                        </div>

                        <div class="max-h-[70vh] overflow-y-auto p-5 space-y-5">
                            <section class="space-y-2">
                                <h5 class="text-sm font-extrabold uppercase tracking-wide text-slate-600">Hari Ini</h5>
                                @if ($todaySectionItems->isEmpty())
                                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                                        Tidak ada status meja untuk hari ini.
                                    </div>
                                @else
                                    <div class="space-y-2">
                                        @foreach ($todaySectionItems as $item)
                                            @php
                                                $entryType = (string) ($item['entryType'] ?? '');
                                                $entry = $item['data'] ?? [];
                                                $statusRaw = strtoupper((string) ($entry['status'] ?? 'UNKNOWN'));
                                                $statusText = match ($statusRaw) {
                                                    'PENDING_PAYMENT' => 'Menunggu Pembayaran',
                                                    'CONFIRMED' => 'Terkonfirmasi',
                                                    'IN_QUEUE' => 'Dalam Antrean',
                                                    'IN_PROGRESS' => 'Sedang Diproses',
                                                    'DELIVERED' => 'Disajikan',
                                                    'PENDING' => 'Menunggu Konfirmasi',
                                                    'SEATED' => 'Sudah Duduk',
                                                    default => ucfirst(strtolower(str_replace('_', ' ', $statusRaw))),
                                                };
                                                $startLabel = isset($entry['bookingStartAt']) && $entry['bookingStartAt']
                                                    ? \Illuminate\Support\Carbon::parse($entry['bookingStartAt'])->timezone('Asia/Jakarta')->format('d M Y H:i')
                                                    : null;
                                                $endLabel = isset($entry['bookingEndAt']) && $entry['bookingEndAt']
                                                    ? \Illuminate\Support\Carbon::parse($entry['bookingEndAt'])->timezone('Asia/Jakarta')->format('d M Y H:i')
                                                    : null;
                                            @endphp
                                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                                <p class="font-bold text-[var(--rich-black)]">
                                                    {{ $entryType === 'order' ? 'On The Spot' : 'Booking Hari Ini' }} - {{ $entry['displayId'] ?? '-' }}
                                                </p>
                                                <p>{{ $entry['customerName'] ?? '-' }} ({{ $entry['customerEmail'] ?? '-' }})</p>
                                                <p>Status: {{ $statusText }}</p>
                                                @if ($startLabel)
                                                    <p>Mulai: {{ $startLabel }}</p>
                                                @endif
                                                @if ($endLabel)
                                                    <p>Selesai: {{ $endLabel }}</p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </section>

                            <section class="space-y-2">
                                <h5 class="text-sm font-extrabold uppercase tracking-wide text-slate-600">Booking Mendatang (Bukan Hari Ini)</h5>
                                @if ($upcomingSectionItems->isEmpty())
                                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                                        Tidak ada booking mendatang di luar hari ini.
                                    </div>
                                @else
                                    <div class="space-y-2" data-upcoming-list="{{ $tableId }}">
                                        @foreach ($upcomingSectionItems as $item)
                                            @php
                                                $entry = $item['data'] ?? [];
                                                $statusRaw = strtoupper((string) ($entry['status'] ?? 'UNKNOWN'));
                                                $statusText = match ($statusRaw) {
                                                    'PENDING' => 'Menunggu Konfirmasi',
                                                    'CONFIRMED' => 'Terkonfirmasi',
                                                    'SEATED' => 'Sudah Duduk',
                                                    default => ucfirst(strtolower(str_replace('_', ' ', $statusRaw))),
                                                };
                                                $startLabel = isset($entry['bookingStartAt']) && $entry['bookingStartAt']
                                                    ? \Illuminate\Support\Carbon::parse($entry['bookingStartAt'])->timezone('Asia/Jakarta')->format('d M Y H:i')
                                                    : '-';
                                                $endLabel = isset($entry['bookingEndAt']) && $entry['bookingEndAt']
                                                    ? \Illuminate\Support\Carbon::parse($entry['bookingEndAt'])->timezone('Asia/Jakarta')->format('d M Y H:i')
                                                    : '-';
                                            @endphp
                                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700" data-upcoming-item="{{ $tableId }}">
                                                <p class="font-bold text-[var(--rich-black)]">Booking - {{ $entry['displayId'] ?? '-' }}</p>
                                                <p>{{ $entry['customerName'] ?? '-' }} ({{ $entry['customerEmail'] ?? '-' }})</p>
                                                <p>Status: {{ $statusText }}</p>
                                                <p>Mulai: {{ $startLabel }}</p>
                                                <p>Selesai: {{ $endLabel }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs" data-upcoming-pagination="{{ $tableId }}">
                                        <button type="button" class="rounded-md border border-slate-300 px-2 py-1 font-bold text-slate-600 disabled:opacity-40" data-upcoming-prev="{{ $tableId }}">
                                            Sebelumnya
                                        </button>
                                        <span class="font-semibold text-slate-600" data-upcoming-page-label="{{ $tableId }}">Halaman 1</span>
                                        <button type="button" class="rounded-md border border-slate-300 px-2 py-1 font-bold text-slate-600 disabled:opacity-40" data-upcoming-next="{{ $tableId }}">
                                            Berikutnya
                                        </button>
                                    </div>
                                @endif
                            </section>
                        </div>
                    </div>
                </div>
            @empty
                <article class="md:col-span-2 xl:col-span-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <p class="text-sm font-semibold text-slate-500">Belum ada data meja.</p>
                </article>
            @endforelse
        </section>
    </section>

    <script>
        (function () {
            const pageSize = 3;
            const paginations = document.querySelectorAll('[data-upcoming-pagination]');

            paginations.forEach(function (paginationEl) {
                const tableId = paginationEl.getAttribute('data-upcoming-pagination');
                const items = Array.from(document.querySelectorAll('[data-upcoming-item="' + tableId + '"]'));
                const prevBtn = paginationEl.querySelector('[data-upcoming-prev="' + tableId + '"]');
                const nextBtn = paginationEl.querySelector('[data-upcoming-next="' + tableId + '"]');
                const pageLabel = paginationEl.querySelector('[data-upcoming-page-label="' + tableId + '"]');

                if (!items.length || !prevBtn || !nextBtn || !pageLabel) {
                    paginationEl.classList.add('hidden');
                    return;
                }

                let currentPage = 1;
                const totalPages = Math.max(1, Math.ceil(items.length / pageSize));

                function render() {
                    const start = (currentPage - 1) * pageSize;
                    const end = start + pageSize;

                    items.forEach(function (item, index) {
                        item.classList.toggle('hidden', !(index >= start && index < end));
                    });

                    pageLabel.textContent = 'Halaman ' + currentPage + ' dari ' + totalPages;
                    prevBtn.disabled = currentPage === 1;
                    nextBtn.disabled = currentPage === totalPages;
                }

                prevBtn.addEventListener('click', function () {
                    if (currentPage > 1) {
                        currentPage -= 1;
                        render();
                    }
                });

                nextBtn.addEventListener('click', function () {
                    if (currentPage < totalPages) {
                        currentPage += 1;
                        render();
                    }
                });

                render();
            });
        })();

        document.addEventListener('click', function (event) {
            const openTrigger = event.target.closest('[data-table-detail-open]');
            if (openTrigger) {
                const tableId = openTrigger.getAttribute('data-table-detail-open');
                const modal = document.querySelector('[data-table-detail-modal="' + tableId + '"]');
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
                return;
            }

            const closeTrigger = event.target.closest('[data-table-detail-close]');
            if (closeTrigger) {
                const tableId = closeTrigger.getAttribute('data-table-detail-close');
                const modal = document.querySelector('[data-table-detail-modal="' + tableId + '"]');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
                return;
            }

            const modalBackdrop = event.target.closest('[data-table-detail-modal]');
            if (modalBackdrop && event.target === modalBackdrop) {
                modalBackdrop.classList.add('hidden');
                modalBackdrop.classList.remove('flex');
            }
        });

        document.querySelectorAll('form[data-clear-guard="true"]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                const message = form.getAttribute('data-clear-guard-message')
                    || 'Pesanan belum diserahkan.';
                if (window.KedaiKlikNotify && typeof window.KedaiKlikNotify.confirm === 'function') {
                    window.KedaiKlikNotify.confirm({
                        type: 'warning',
                        badge: 'Perhatian',
                        title: 'Meja belum bisa dikosongkan',
                        message: message,
                        confirmText: 'OK',
                        singleButton: true,
                    });
                }
            });
        });
    </script>

</x-backoffice.layout>
