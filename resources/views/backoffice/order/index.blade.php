<x-backoffice.layout pageTitle="Kelola Pesanan">
    <section class="space-y-5">
        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 class="text-xl md:text-2xl font-extrabold text-[var(--rich-black)]">Antrian Pesanan Hari Ini</h2>
                    <p class="text-sm font-semibold text-slate-500">Menampilkan antrian aktif untuk {{ $businessDateLabel ?? '-' }} </p>
                </div>
                <span class="inline-flex items-center rounded-full border border-[#6A2B09]/20 bg-[#FCB861]/20 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-[#6A2B09]">
                    Fokus Operasional Hari Ini
                </span>
            </div>

            <div class="mt-4 grid grid-cols-2 lg:grid-cols-6 gap-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total Order</p>
                    <p class="mt-1 text-xl font-extrabold text-[var(--rich-black)]">{{ (int) ($summary['total'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-cyan-700">Total Booking</p>
                    <p class="mt-1 text-xl font-extrabold text-cyan-800">{{ (int) ($summary['booking_total'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-teal-200 bg-teal-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-teal-700">Terkonfirmasi</p>
                    <p class="mt-1 text-xl font-extrabold text-teal-800">{{ (int) ($summary['confirmed'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Dalam Antrean</p>
                    <p class="mt-1 text-xl font-extrabold text-amber-800">{{ (int) ($summary['in_queue'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Sedang Diproses</p>
                    <p class="mt-1 text-xl font-extrabold text-blue-800">{{ (int) ($summary['processing'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Disajikan</p>
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
                        <button type="button" class="order-status-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-status="booking_dine_in">Booking Dine-In</button>
                    </div>
                </div>

                <div class="lg:col-span-4">
                    <select id="order-sort" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[var(--rajah)]/70 focus:border-[var(--rajah)]">
                        <option value="queue-asc" selected>Sort by: Antrian (Terlama ke Terbaru)</option>
                        <option value="default">Urutan Data Awal</option>
                        <option value="queue-desc">No Antrian Terbesar</option>
                        <option value="total-asc">Total Termurah</option>
                        <option value="total-desc">Total Termahal</option>
                        <option value="table-asc">Nomor Meja Kecil</option>
                        <option value="table-desc">Nomor Meja Besar</option>
                        <option value="pickup_takeaway">Pickup/Take Away</option>
                    </select>
                </div>
            </div>
        </article>

        <section id="today-queue-section" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                <h3 id="today-section-title" class="text-sm font-extrabold uppercase tracking-wide text-slate-600">Antrian Hari Ini</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left">
                    <thead class="bg-white border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
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
                    <tbody id="order-today-table-body" class="divide-y divide-slate-200" data-section="today">
                        @forelse (($todayOrders ?? []) as $order)
                            @php
                                $status = strtoupper((string) ($order['status'] ?? 'UNKNOWN'));
                                $queueNumber = (int) ($order['queueNumber'] ?? 0);
                                $tableNumber = (int) ($order['tableNumber'] ?? 0);
                                $totalPrice = (float) ($order['totalPrice'] ?? 0);
                                $orderId = (string) ($order['orderId'] ?? '');
                                $sourceType = strtoupper((string) ($order['sourceType'] ?? 'ORDER'));
                                $isBookingRow = $sourceType === 'BOOKING';
                                $orderType = strtolower((string) ($order['orderType'] ?? ''));
                                $isBookingDineIn = $isBookingRow || $orderType === 'booking_dine_in';
                                $tableDisplayLabel = $tableNumber > 0
                                    ? (string) $tableNumber
                                    : match ($orderType) {
                                        'take_away' => 'Take Away',
                                        'pickup' => 'Pickup',
                                        default => '-',
                                    };
                                $bookingStartAtRaw = (string) (($order['bookingStartAt'] ?? $order['booking_start_at'] ?? '') ?: '');
                                $bookingStartAt = $bookingStartAtRaw !== '' ? \Illuminate\Support\Carbon::parse($bookingStartAtRaw)->setTimezone('Asia/Jakarta') : null;
                                $bookingDuration = (int) ($order['durationHours'] ?? $order['duration_hours'] ?? 0);
                                $displayId = (string) ($order['displayId'] ?? ($isBookingRow
                                    ? 'BKG-' . strtoupper(substr(str_replace('BOOKING:', '', $orderId), -6))
                                    : 'ORD-' . strtoupper(substr($orderId, -6))));
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
                            <tr class="order-row" data-order-id="{{ strtolower($displayId) }}" data-customer="{{ strtolower($customerName) }}" data-email="{{ strtolower($customerEmail) }}" data-status="{{ strtolower($status) }}" data-order-type="{{ $isBookingDineIn ? 'booking_dine_in' : $orderType }}" data-total="{{ $totalPrice }}" data-table="{{ $tableNumber }}" data-queue="{{ $queueNumber }}" data-event-ts="{{ (int) ($order['eventTs'] ?? 0) }}">
                                <td class="px-4 py-3 text-sm font-extrabold text-[var(--rich-black)]">{{ $displayId }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $customerName !== '' ? $customerName : '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $customerEmail !== '' ? $customerEmail : '-' }}</td>
                                <td class="px-4 py-3 text-sm font-bold text-slate-700">{{ $queueNumber > 0 ? '#' . $queueNumber : ($isBookingRow ? 'Booking' : '-') }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    <div>{{ $tableDisplayLabel }}</div>
                                    @if ($isBookingDineIn && $bookingStartAt)
                                        <div class="mt-1 text-[11px] font-semibold text-slate-500">
                                            {{ $bookingStartAt->format('d-m-Y') }} • {{ $bookingStartAt->format('H:i') }}
                                            @if ($bookingDuration > 0)
                                                • {{ $bookingDuration }} jam
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3"><span data-order-status-badge class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                <td class="px-4 py-3 text-sm font-extrabold text-[var(--philippine-bronze)]">Rp {{ number_format($totalPrice, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        @if ($isBookingRow)
                                            <a href="/backoffice/booking" class="inline-flex items-center rounded-lg border border-[#2563EB] bg-white hover:bg-blue-50 text-[#2563EB] text-xs font-extrabold px-3 py-2 transition">Kelola Booking</a>
                                        @else
                                            <form method="POST" action="/backoffice/daftar_pesanan/{{ urlencode($orderId) }}/status" class="flex items-center gap-2" data-order-status-form>
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
                                            <a href="/backoffice/daftar_pesanan?detail={{ urlencode($orderId) }}" data-modal-link class="inline-flex items-center rounded-lg border border-[#2563EB] bg-white hover:bg-blue-50 text-[#2563EB] text-xs font-extrabold px-3 py-2 transition">Lihat Detail</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="order-empty-row">
                                <td colspan="8" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">Belum ada antrian aktif hari ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div id="order-today-empty" class="hidden px-4 py-8 text-center border-t border-slate-200">
                <p class="text-sm font-semibold text-slate-500">Pesanan hari ini tidak ditemukan untuk filter ini.</p>
            </div>
        </section>

        <section id="delivered-section" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 bg-emerald-50">
                <h3 class="text-sm font-extrabold uppercase tracking-wide text-emerald-700">Disajikan Hari Ini</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left">
                    <thead class="bg-white border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
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
                    <tbody id="order-delivered-table-body" class="divide-y divide-slate-200" data-section="delivered">
                        @forelse (($todayDeliveredOrders ?? []) as $order)
                            @php
                                $status = strtoupper((string) ($order['status'] ?? 'UNKNOWN'));
                                $queueNumber = (int) ($order['queueNumber'] ?? 0);
                                $tableNumber = (int) ($order['tableNumber'] ?? 0);
                                $totalPrice = (float) ($order['totalPrice'] ?? 0);
                                $orderId = (string) ($order['orderId'] ?? '');
                                $sourceType = strtoupper((string) ($order['sourceType'] ?? 'ORDER'));
                                $isBookingRow = $sourceType === 'BOOKING';
                                $orderType = strtolower((string) ($order['orderType'] ?? ''));
                                $isBookingDineIn = $isBookingRow || $orderType === 'booking_dine_in';
                                $tableDisplayLabel = $tableNumber > 0
                                    ? (string) $tableNumber
                                    : match ($orderType) {
                                        'take_away' => 'Take Away',
                                        'pickup' => 'Pickup',
                                        default => '-',
                                    };
                                $bookingStartAtRaw = (string) (($order['bookingStartAt'] ?? $order['booking_start_at'] ?? '') ?: '');
                                $bookingStartAt = $bookingStartAtRaw !== '' ? \Illuminate\Support\Carbon::parse($bookingStartAtRaw)->setTimezone('Asia/Jakarta') : null;
                                $bookingDuration = (int) ($order['durationHours'] ?? $order['duration_hours'] ?? 0);
                                $displayId = (string) ($order['displayId'] ?? ($isBookingRow
                                    ? 'BKG-' . strtoupper(substr(str_replace('BOOKING:', '', $orderId), -6))
                                    : 'ORD-' . strtoupper(substr($orderId, -6))));
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
                            <tr class="order-row" data-order-id="{{ strtolower($displayId) }}" data-customer="{{ strtolower($customerName) }}" data-email="{{ strtolower($customerEmail) }}" data-status="{{ strtolower($status) }}" data-order-type="{{ $isBookingDineIn ? 'booking_dine_in' : $orderType }}" data-total="{{ $totalPrice }}" data-table="{{ $tableNumber }}" data-queue="{{ $queueNumber }}" data-event-ts="{{ (int) ($order['eventTs'] ?? 0) }}">
                                <td class="px-4 py-3 text-sm font-extrabold text-[var(--rich-black)]">{{ $displayId }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $customerName !== '' ? $customerName : '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $customerEmail !== '' ? $customerEmail : '-' }}</td>
                                <td class="px-4 py-3 text-sm font-bold text-slate-700">{{ $queueNumber > 0 ? '#' . $queueNumber : ($isBookingRow ? 'Booking' : '-') }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    <div>{{ $tableDisplayLabel }}</div>
                                    @if ($isBookingDineIn && $bookingStartAt)
                                        <div class="mt-1 text-[11px] font-semibold text-slate-500">
                                            {{ $bookingStartAt->format('d-m-Y') }} • {{ $bookingStartAt->format('H:i') }}
                                            @if ($bookingDuration > 0)
                                                • {{ $bookingDuration }} jam
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3"><span data-order-status-badge class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                <td class="px-4 py-3 text-sm font-extrabold text-[var(--philippine-bronze)]">Rp {{ number_format($totalPrice, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        @if ($isBookingRow)
                                            <a href="/backoffice/booking" class="inline-flex items-center rounded-lg border border-[#2563EB] bg-white hover:bg-blue-50 text-[#2563EB] text-xs font-extrabold px-3 py-2 transition">Kelola Booking</a>
                                        @else
                                            <form method="POST" action="/backoffice/daftar_pesanan/{{ urlencode($orderId) }}/status" class="flex items-center gap-2" data-order-status-form>
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
                                            <a href="/backoffice/daftar_pesanan?detail={{ urlencode($orderId) }}" data-modal-link class="inline-flex items-center rounded-lg border border-[#2563EB] bg-white hover:bg-blue-50 text-[#2563EB] text-xs font-extrabold px-3 py-2 transition">Lihat Detail</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="order-empty-row">
                                <td colspan="8" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">Belum ada pesanan yang disajikan hari ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div id="order-delivered-empty" class="hidden px-4 py-8 text-center border-t border-slate-200">
                <p class="text-sm font-semibold text-slate-500">Pesanan disajikan hari ini tidak ditemukan untuk filter ini.</p>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <button id="order-history-toggle" type="button" class="flex w-full items-center justify-between px-4 py-4 text-left transition hover:bg-slate-50">
                <div>
                    <p class="text-sm font-extrabold uppercase tracking-wide text-slate-700">Riwayat Sebelumnya</p>
                    <p class="text-xs font-semibold text-slate-500">{{ (int) (($previousOrders ?? collect())->count()) }} pesanan dari hari sebelum {{ $businessDateLabel ?? '-' }}</p>
                </div>
                <span id="order-history-icon" class="text-lg font-black text-slate-500">+</span>
            </button>

            <div id="order-history-panel" class="hidden border-t border-slate-200">
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
                        <tbody id="order-previous-table-body" class="divide-y divide-slate-200" data-section="previous">
                            @forelse (($previousOrders ?? []) as $order)
                                @php
                                    $status = strtoupper((string) ($order['status'] ?? 'UNKNOWN'));
                                    $queueNumber = (int) ($order['queueNumber'] ?? 0);
                                    $tableNumber = (int) ($order['tableNumber'] ?? 0);
                                    $totalPrice = (float) ($order['totalPrice'] ?? 0);
                                    $orderId = (string) ($order['orderId'] ?? '');
                                    $sourceType = strtoupper((string) ($order['sourceType'] ?? 'ORDER'));
                                    $isBookingRow = $sourceType === 'BOOKING';
                                    $orderType = strtolower((string) ($order['orderType'] ?? ''));
                                    $isBookingDineIn = $isBookingRow || $orderType === 'booking_dine_in';
                                    $tableDisplayLabel = $tableNumber > 0
                                        ? (string) $tableNumber
                                        : match ($orderType) {
                                            'take_away' => 'Take Away',
                                            'pickup' => 'Pickup',
                                            default => '-',
                                        };
                                    $bookingStartAtRaw = (string) (($order['bookingStartAt'] ?? $order['booking_start_at'] ?? '') ?: '');
                                    $bookingStartAt = $bookingStartAtRaw !== '' ? \Illuminate\Support\Carbon::parse($bookingStartAtRaw)->setTimezone('Asia/Jakarta') : null;
                                    $bookingDuration = (int) ($order['durationHours'] ?? $order['duration_hours'] ?? 0);
                                    $displayId = (string) ($order['displayId'] ?? ($isBookingRow
                                        ? 'BKG-' . strtoupper(substr(str_replace('BOOKING:', '', $orderId), -6))
                                        : 'ORD-' . strtoupper(substr($orderId, -6))));
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
                                <tr class="order-row" data-order-id="{{ strtolower($displayId) }}" data-customer="{{ strtolower($customerName) }}" data-email="{{ strtolower($customerEmail) }}" data-status="{{ strtolower($status) }}" data-order-type="{{ $isBookingDineIn ? 'booking_dine_in' : $orderType }}" data-total="{{ $totalPrice }}" data-table="{{ $tableNumber }}" data-queue="{{ $queueNumber }}" data-event-ts="{{ (int) ($order['eventTs'] ?? 0) }}">
                                    <td class="px-4 py-3 text-sm font-extrabold text-[var(--rich-black)]">{{ $displayId }}</td>
                                    <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $customerName !== '' ? $customerName : '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ $customerEmail !== '' ? $customerEmail : '-' }}</td>
                                    <td class="px-4 py-3 text-sm font-bold text-slate-700">{{ $queueNumber > 0 ? '#' . $queueNumber : ($isBookingRow ? 'Booking' : '-') }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        <div>{{ $tableDisplayLabel }}</div>
                                        @if ($isBookingDineIn && $bookingStartAt)
                                            <div class="mt-1 text-[11px] font-semibold text-slate-500">
                                                {{ $bookingStartAt->format('d-m-Y') }} • {{ $bookingStartAt->format('H:i') }}
                                                @if ($bookingDuration > 0)
                                                    • {{ $bookingDuration }} jam
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                <td class="px-4 py-3"><span data-order-status-badge class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                    <td class="px-4 py-3 text-sm font-extrabold text-[var(--philippine-bronze)]">Rp {{ number_format($totalPrice, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            @if ($isBookingRow)
                                                <a href="/backoffice/booking" class="inline-flex items-center rounded-lg border border-[#2563EB] bg-white hover:bg-blue-50 text-[#2563EB] text-xs font-extrabold px-3 py-2 transition">Kelola Booking</a>
                                            @else
                                                <form method="POST" action="/backoffice/daftar_pesanan/{{ urlencode($orderId) }}/status" class="flex items-center gap-2" data-order-status-form>
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
                                                <a href="/backoffice/daftar_pesanan?detail={{ urlencode($orderId) }}" data-modal-link class="inline-flex items-center rounded-lg border border-[#2563EB] bg-white hover:bg-blue-50 text-[#2563EB] text-xs font-extrabold px-3 py-2 transition">Lihat Detail</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="order-empty-row">
                                    <td colspan="8" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">Belum ada riwayat pesanan sebelumnya.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div id="order-previous-empty" class="hidden px-4 py-8 text-center border-t border-slate-200">
                    <p class="text-sm font-semibold text-slate-500">Riwayat pesanan tidak ditemukan untuk filter ini.</p>
                </div>
            </div>
        </section>
    </section>

    <script>
        (function () {
            const searchInput = document.getElementById('order-search');
            const sortSelect = document.getElementById('order-sort');
            const tabs = Array.from(document.querySelectorAll('.order-status-tab'));
            const historyToggle = document.getElementById('order-history-toggle');
            const historyPanel = document.getElementById('order-history-panel');
            const historyIcon = document.getElementById('order-history-icon');
            const todayQueueSection = document.getElementById('today-queue-section');
            const todaySectionTitle = document.getElementById('today-section-title');
            const deliveredSection = document.getElementById('delivered-section');
            const sections = [
                {
                    body: document.getElementById('order-today-table-body'),
                    empty: document.getElementById('order-today-empty'),
                },
                {
                    body: document.getElementById('order-delivered-table-body'),
                    empty: document.getElementById('order-delivered-empty'),
                },
                {
                    body: document.getElementById('order-previous-table-body'),
                    empty: document.getElementById('order-previous-empty'),
                },
            ].filter(function (section) {
                return section.body;
            });

            if (sections.length === 0) {
                return;
            }

            const baseOrder = new Map();
            const rowsBySection = sections.map(function (section) {
                const rows = Array.from(section.body.querySelectorAll('.order-row'));
                rows.forEach(function (row, index) {
                    baseOrder.set(row, index);
                });
                return rows;
            });

            let activeStatus = 'all';
            const statusSectionTitle = {
                all: 'Antrian Hari Ini',
                confirmed: 'Terkonfirmasi Hari Ini',
                in_queue: 'Dalam Antrean Hari Ini',
                in_progress: 'Sedang Diproses Hari Ini',
                delivered: 'Disajikan Hari Ini',
                booking_dine_in: 'Booking Dine-In Hari Ini',
            };

            function normalize(text) {
                return String(text || '').toLowerCase().trim();
            }

            function sortRows(list) {
                const mode = sortSelect ? sortSelect.value : 'queue-asc';
                const isPickupOrTakeAway = function (row) {
                    const type = normalize(row.dataset.orderType);
                    return type === 'pickup' || type === 'take_away';
                };
                const tableRank = function (row) {
                    const table = Number(row.dataset.table || 0);
                    if (table > 0) {
                        return table;
                    }
                    return null;
                };

                if (mode === 'default') {
                    return list.sort((a, b) => baseOrder.get(a) - baseOrder.get(b));
                }
                if (mode === 'queue-asc') {
                    return list.sort((a, b) => Number(a.dataset.eventTs || 0) - Number(b.dataset.eventTs || 0));
                }
                if (mode === 'queue-desc') {
                    return list.sort((a, b) => Number(b.dataset.eventTs || 0) - Number(a.dataset.eventTs || 0));
                }
                if (mode === 'total-asc') {
                    return list.sort((a, b) => Number(a.dataset.total || 0) - Number(b.dataset.total || 0));
                }
                if (mode === 'total-desc') {
                    return list.sort((a, b) => Number(b.dataset.total || 0) - Number(a.dataset.total || 0));
                }
                if (mode === 'table-asc') {
                    return list.sort((a, b) => {
                        const aTable = tableRank(a);
                        const bTable = tableRank(b);
                        if (aTable === null && bTable === null) return 0;
                        if (aTable === null) return 1;
                        if (bTable === null) return -1;
                        return aTable - bTable;
                    });
                }
                if (mode === 'table-desc') {
                    return list.sort((a, b) => {
                        const aTable = tableRank(a);
                        const bTable = tableRank(b);
                        if (aTable === null && bTable === null) return 0;
                        if (aTable === null) return 1;
                        if (bTable === null) return -1;
                        return bTable - aTable;
                    });
                }
                if (mode === 'pickup_takeaway') {
                    return list.sort((a, b) => {
                        const aPriority = isPickupOrTakeAway(a) ? 0 : 1;
                        const bPriority = isPickupOrTakeAway(b) ? 0 : 1;
                        if (aPriority !== bPriority) return aPriority - bPriority;
                        return Number(a.dataset.eventTs || 0) - Number(b.dataset.eventTs || 0);
                    });
                }

                return list;
            }

            function applyToSection(section, rows) {
                const keyword = normalize(searchInput ? searchInput.value : '');

                let visible = rows.filter(function (row) {
                    const orderId = normalize(row.dataset.orderId);
                    const customer = normalize(row.dataset.customer);
                    const email = normalize(row.dataset.email);
                    const table = normalize(row.dataset.table);
                    const status = normalize(row.dataset.status);
                    const orderType = normalize(row.dataset.orderType);

                    const bySearch = keyword === '' || orderId.includes(keyword) || customer.includes(keyword) || email.includes(keyword) || table.includes(keyword);
                    const byStatus = activeStatus === 'all'
                        || (activeStatus === 'booking_dine_in' ? orderType === 'booking_dine_in' : status === activeStatus);

                    return bySearch && byStatus;
                });

                visible = sortRows(visible);

                rows.forEach(function (row) {
                    row.classList.add('hidden');
                });

                visible.forEach(function (row) {
                    row.classList.remove('hidden');
                    section.body.appendChild(row);
                });

                if (section.empty) {
                    if (rows.length === 0) {
                        section.empty.classList.add('hidden');
                        return;
                    }
                    section.empty.classList.toggle('hidden', visible.length !== 0);
                }
            }

            function applyFilters() {
                if (todaySectionTitle) {
                    todaySectionTitle.textContent = statusSectionTitle[activeStatus] || 'Antrian Hari Ini';
                }

                const isAllTab = activeStatus === 'all';
                const isDeliveredTab = activeStatus === 'delivered';
                const isBookingTab = activeStatus === 'booking_dine_in';

                if (todayQueueSection) {
                    todayQueueSection.classList.toggle('hidden', isDeliveredTab);
                }

                if (deliveredSection) {
                    deliveredSection.classList.toggle('hidden', !(isAllTab || isDeliveredTab || isBookingTab));
                }

                sections.forEach(function (section, index) {
                    applyToSection(section, rowsBySection[index]);
                });
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

            if (historyToggle && historyPanel && historyIcon) {
                historyToggle.addEventListener('click', function () {
                    const isHidden = historyPanel.classList.contains('hidden');
                    historyPanel.classList.toggle('hidden', !isHidden);
                    historyIcon.textContent = isHidden ? '−' : '+';
                });
            }

            const statusMeta = {
                CONFIRMED: { label: 'Terkonfirmasi', className: 'bg-amber-100 text-amber-700' },
                IN_QUEUE: { label: 'Dalam Antrean', className: 'bg-orange-100 text-orange-700' },
                IN_PROGRESS: { label: 'Sedang Diproses', className: 'bg-blue-100 text-blue-700' },
                DELIVERED: { label: 'Disajikan', className: 'bg-emerald-100 text-emerald-700' },
            };
            const statusBadgeBaseClass = 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold';

            document.addEventListener('submit', async function (event) {
                const form = event.target instanceof HTMLFormElement ? event.target : null;
                if (!form || !form.matches('[data-order-status-form]')) {
                    return;
                }

                event.preventDefault();

                const submitButton = form.querySelector('button[type="submit"]');
                const statusSelect = form.querySelector('select[name="status"]');
                const statusValue = statusSelect ? String(statusSelect.value || '').toUpperCase() : '';
                const row = form.closest('.order-row');
                const statusBadge = row ? row.querySelector('[data-order-status-badge]') : null;
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                if (!statusSelect || !statusValue) {
                    return;
                }

                form.dataset.loading = '1';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.classList.add('opacity-60', 'cursor-not-allowed');
                }

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        },
                        body: new URLSearchParams({
                            _token: csrfToken,
                            _method: 'PATCH',
                            status: statusValue,
                        }),
                    });

                    const payload = await response.json().catch(function () { return {}; });
                    if (!response.ok) {
                        throw new Error(payload.message || 'Gagal memperbarui status.');
                    }

                    if (row) {
                        row.dataset.status = String(statusValue).toLowerCase();
                    }

                    if (statusBadge) {
                        const nextMeta = statusMeta[statusValue] || { label: statusValue, className: 'bg-slate-100 text-slate-700' };
                        statusBadge.className = statusBadgeBaseClass + ' ' + nextMeta.className;
                        statusBadge.textContent = nextMeta.label;
                    }

                    if (window.KedaiKlikNotify && typeof window.KedaiKlikNotify.show === 'function') {
                        window.KedaiKlikNotify.show({
                            type: 'success',
                            title: 'Status diperbarui',
                            message: payload.message || 'Status pesanan berhasil diperbarui.',
                        });
                    }

                    applyFilters();
                } catch (error) {
                    if (window.KedaiKlikNotify && typeof window.KedaiKlikNotify.show === 'function') {
                        window.KedaiKlikNotify.show({
                            type: 'error',
                            title: 'Gagal memperbarui',
                            message: error.message || 'Terjadi kesalahan saat memperbarui status.',
                        });
                    }
                } finally {
                    delete form.dataset.loading;
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.classList.remove('opacity-60', 'cursor-not-allowed');
                    }
                }
            });

            applyFilters();
        })();
    </script>

    @include('backoffice.order.detail.detail', ['selectedOrder' => $selectedOrder])
</x-backoffice.layout>
