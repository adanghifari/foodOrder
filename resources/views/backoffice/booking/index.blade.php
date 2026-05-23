<x-backoffice.layout pageTitle="Kelola Booking">
    <section class="space-y-5">
        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 class="text-xl md:text-2xl font-extrabold text-[var(--rich-black)]">Kelola Booking</h2>
                    <p class="text-sm font-semibold text-slate-500">Daftar booking untuk {{ $bookingDateLabel ?? '-' }} dan jadwal akan datang.</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Booking Hari Ini</p>
                    <p class="mt-1 text-xl font-extrabold text-[var(--rich-black)]">{{ (int) ($summary['today'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-indigo-700">Jadwal Akan Datang</p>
                    <p class="mt-1 text-xl font-extrabold text-indigo-800">{{ (int) ($summary['upcoming'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Sedang Berjalan</p>
                    <p class="mt-1 text-xl font-extrabold text-blue-800">{{ (int) ($summary['running'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Selesai Hari Ini</p>
                    <p class="mt-1 text-xl font-extrabold text-emerald-800">{{ (int) ($summary['completed'] ?? 0) }}</p>
                </div>
            </div>
        </article>

        @php
            $renderRows = function ($rows) {
                foreach (($rows ?? []) as $booking) {
                    $status = strtoupper((string) ($booking['status'] ?? 'UNKNOWN'));
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

                    $startAt = '';
                    $endAt = '';
                    try {
                        $startAt = \Illuminate\Support\Carbon::parse((string) ($booking['bookingStartAt'] ?? ''))->timezone('Asia/Jakarta')->format('d M Y H:i');
                        $endAt = \Illuminate\Support\Carbon::parse((string) ($booking['bookingEndAt'] ?? ''))->timezone('Asia/Jakarta')->format('d M Y H:i');
                    } catch (\Throwable $exception) {
                        $startAt = '-';
                        $endAt = '-';
                    }

                    echo '<tr class="border-b border-slate-200">';
                    echo '<td class="px-4 py-3 text-sm font-extrabold text-[var(--rich-black)]">' . e((string) ($booking['displayId'] ?? '-')) . '</td>';
                    echo '<td class="px-4 py-3 text-sm text-slate-700">Meja ' . e((string) ((int) ($booking['tableNumber'] ?? 0))) . '</td>';
                    echo '<td class="px-4 py-3 text-sm text-slate-700">' . e((string) ($booking['customerName'] ?? '-')) . '</td>';
                    echo '<td class="px-4 py-3 text-sm text-slate-700">' . e($startAt) . '</td>';
                    echo '<td class="px-4 py-3 text-sm text-slate-700">' . e($endAt) . '</td>';
                    echo '<td class="px-4 py-3 text-sm text-slate-700">' . e((string) ((int) ($booking['durationHours'] ?? 0))) . ' jam</td>';
                    echo '<td class="px-4 py-3 text-sm font-bold text-[var(--philippine-bronze)]">Rp ' . e(number_format((int) ($booking['extraCharge'] ?? 0), 0, ',', '.')) . '</td>';
                    echo '<td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ' . e($statusClass) . '">' . e($statusLabel) . '</span></td>';
                    echo '<td class="px-4 py-3"><a href="/backoffice/booking?detail=' . urlencode((string) ($booking['bookingId'] ?? '')) . '" data-modal-link class="inline-flex items-center rounded-lg border border-[#2563EB] bg-white hover:bg-blue-50 text-[#2563EB] text-xs font-extrabold px-3 py-2 transition">Lihat Detail</a></td>';
                    echo '</tr>';
                }
            };
        @endphp

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                <h3 class="text-sm font-extrabold uppercase tracking-wide text-slate-600">Booking Hari Ini</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px] text-left">
                    <thead class="bg-white border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-bold">Booking ID</th>
                            <th class="px-4 py-3 font-bold">Meja</th>
                            <th class="px-4 py-3 font-bold">Nama</th>
                            <th class="px-4 py-3 font-bold">Mulai</th>
                            <th class="px-4 py-3 font-bold">Selesai</th>
                            <th class="px-4 py-3 font-bold">Durasi</th>
                            <th class="px-4 py-3 font-bold">Charge</th>
                            <th class="px-4 py-3 font-bold">Status</th>
                            <th class="px-4 py-3 font-bold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (collect($todayBookings ?? [])->isEmpty())
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-sm font-semibold text-slate-500">Belum ada booking hari ini.</td>
                            </tr>
                        @else
                            {!! $renderRows($todayBookings ?? []) !!}
                        @endif
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 bg-indigo-50">
                <h3 class="text-sm font-extrabold uppercase tracking-wide text-indigo-700">Jadwal Akan Datang</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px] text-left">
                    <thead class="bg-white border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-bold">Booking ID</th>
                            <th class="px-4 py-3 font-bold">Meja</th>
                            <th class="px-4 py-3 font-bold">Nama</th>
                            <th class="px-4 py-3 font-bold">Mulai</th>
                            <th class="px-4 py-3 font-bold">Selesai</th>
                            <th class="px-4 py-3 font-bold">Durasi</th>
                            <th class="px-4 py-3 font-bold">Charge</th>
                            <th class="px-4 py-3 font-bold">Status</th>
                            <th class="px-4 py-3 font-bold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (collect($upcomingBookings ?? [])->isEmpty())
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-sm font-semibold text-slate-500">Belum ada booking jadwal akan datang.</td>
                            </tr>
                        @else
                            {!! $renderRows($upcomingBookings ?? []) !!}
                        @endif
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                <h3 class="text-sm font-extrabold uppercase tracking-wide text-slate-600">Riwayat Booking Sebelumnya</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px] text-left">
                    <thead class="bg-white border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-bold">Booking ID</th>
                            <th class="px-4 py-3 font-bold">Meja</th>
                            <th class="px-4 py-3 font-bold">Nama</th>
                            <th class="px-4 py-3 font-bold">Mulai</th>
                            <th class="px-4 py-3 font-bold">Selesai</th>
                            <th class="px-4 py-3 font-bold">Durasi</th>
                            <th class="px-4 py-3 font-bold">Charge</th>
                            <th class="px-4 py-3 font-bold">Status</th>
                            <th class="px-4 py-3 font-bold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (collect($previousBookings ?? [])->isEmpty())
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-sm font-semibold text-slate-500">Belum ada riwayat booking sebelumnya.</td>
                            </tr>
                        @else
                            {!! $renderRows($previousBookings ?? []) !!}
                        @endif
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    @include('backoffice.booking.detail.detail', ['selectedBooking' => $selectedBooking ?? null])
</x-backoffice.layout>
