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

                    @if ($currentOrder)
                        <div class="mt-3 rounded-xl border border-red-200 bg-white/80 p-3 text-sm text-slate-700 space-y-1">
                            <p class="font-bold text-[var(--rich-black)]">{{ $currentOrder['displayId'] ?? '-' }}</p>
                            <p>Customer: {{ $currentOrder['customerName'] ?? '-' }}</p>
                            <p>Email: {{ $currentOrder['customerEmail'] ?? '-' }}</p>
                            <p>Status: {{ str_replace('_', ' ', (string) ($currentOrder['status'] ?? 'UNKNOWN')) }}</p>
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
                                class="w-full inline-flex items-center justify-center rounded-lg border border-red-200 bg-white hover:bg-red-50 text-red-700 text-xs font-extrabold px-3 py-2 transition"
                            >
                                Kosongkan Meja
                            </button>
                        </form>
                    @endif
                </article>
            @empty
                <article class="md:col-span-2 xl:col-span-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <p class="text-sm font-semibold text-slate-500">Belum ada data meja.</p>
                </article>
            @endforelse
        </section>
    </section>

</x-backoffice.layout>
