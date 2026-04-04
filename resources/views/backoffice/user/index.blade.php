<x-backoffice.layout pageTitle="Pengguna">
    <section class="space-y-5">
        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
            <h2 class="text-xl md:text-2xl font-extrabold text-[var(--rich-black)]">Daftar Pengguna</h2>

            <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total Pengguna</p>
                    <p class="mt-1 text-xl font-extrabold text-[var(--rich-black)]">{{ (int) ($summary['total'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-[#6A2B09]/25 bg-[#FCB861]/25 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-[#6A2B09]">Admin</p>
                    <p class="mt-1 text-xl font-extrabold text-[#6A2B09]">{{ (int) ($summary['admin'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Customer</p>
                    <p class="mt-1 text-xl font-extrabold text-blue-800">{{ (int) ($summary['customer'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-100 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-600">Lainnya</p>
                    <p class="mt-1 text-xl font-extrabold text-slate-800">{{ (int) ($summary['other'] ?? 0) }}</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 lg:grid-cols-12 gap-3">
                <div class="lg:col-span-8 space-y-3">
                    <input id="user-search" type="text" placeholder="Cari nama / email / no telepon..." class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[var(--rajah)]/70 focus:border-[var(--rajah)]">

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="user-role-tab inline-flex items-center rounded-xl border border-[#6A2B09] bg-[#6A2B09] text-[#FCB861] text-xs font-bold px-3 py-2 transition" data-role="all">Semua</button>
                        <button type="button" class="user-role-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-role="admin">Admin</button>
                        <button type="button" class="user-role-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-role="customer">Customer</button>
                    </div>
                </div>

                <div class="lg:col-span-4">
                    <select id="user-sort" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[var(--rajah)]/70 focus:border-[var(--rajah)]">
                        <option value="default">Sort by: Terbaru</option>
                        <option value="name-asc">Nama A-Z</option>
                        <option value="name-desc">Nama Z-A</option>
                        <option value="role-asc">Role A-Z</option>
                        <option value="role-desc">Role Z-A</option>
                    </select>
                </div>
            </div>
        </article>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left">
                    <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-bold">Foto Profil</th>
                            <th class="px-4 py-3 font-bold">Nama</th>
                            <th class="px-4 py-3 font-bold">Email</th>
                            <th class="px-4 py-3 font-bold">Nomor Telepon</th>
                            <th class="px-4 py-3 font-bold">Role</th>
                        </tr>
                    </thead>
                    <tbody id="user-table-body" class="divide-y divide-slate-200">
                        @forelse (($users ?? []) as $user)
                            @php
                                $role = strtoupper((string) ($user['role'] ?? 'UNKNOWN'));
                                $roleClass = $role === 'ADMIN'
                                    ? 'bg-[#6A2B09]/10 text-[#6A2B09]'
                                    : ($role === 'CUSTOMER' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700');
                            @endphp
                            <tr
                                class="user-row"
                                data-name="{{ strtolower((string) ($user['name'] ?? '')) }}"
                                data-email="{{ strtolower((string) ($user['email'] ?? '')) }}"
                                data-phone="{{ strtolower((string) ($user['phone'] ?? '')) }}"
                                data-role="{{ strtolower($role) }}"
                            >
                                <td class="px-4 py-3">
                                    <img src="{{ $user['photoUrl'] ?? '' }}" alt="Foto {{ $user['name'] ?? '-' }}" class="h-11 w-11 rounded-full object-cover border border-slate-200 bg-slate-100">
                                </td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $user['name'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $user['email'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $user['phone'] ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $roleClass }}">{{ $role }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">Belum ada data pengguna.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="user-filter-empty" class="hidden px-4 py-8 text-center border-t border-slate-200">
                <p class="text-sm font-semibold text-slate-500">Pengguna tidak ditemukan untuk filter ini.</p>
            </div>
        </section>
    </section>

    <script>
        (function () {
            const tableBody = document.getElementById('user-table-body');
            const searchInput = document.getElementById('user-search');
            const sortSelect = document.getElementById('user-sort');
            const tabs = Array.from(document.querySelectorAll('.user-role-tab'));
            const emptyState = document.getElementById('user-filter-empty');

            if (!tableBody) {
                return;
            }

            const rows = Array.from(tableBody.querySelectorAll('.user-row'));
            if (rows.length === 0) {
                return;
            }

            const baseOrder = new Map(rows.map((row, index) => [row, index]));
            let activeRole = 'all';

            function normalize(text) {
                return String(text || '').toLowerCase().trim();
            }

            function sortRows(list) {
                const mode = sortSelect ? sortSelect.value : 'default';

                if (mode === 'default') {
                    return list.sort((a, b) => baseOrder.get(a) - baseOrder.get(b));
                }

                if (mode === 'name-asc') {
                    return list.sort((a, b) => normalize(a.dataset.name).localeCompare(normalize(b.dataset.name)));
                }

                if (mode === 'name-desc') {
                    return list.sort((a, b) => normalize(b.dataset.name).localeCompare(normalize(a.dataset.name)));
                }

                if (mode === 'role-asc') {
                    return list.sort((a, b) => normalize(a.dataset.role).localeCompare(normalize(b.dataset.role)));
                }

                if (mode === 'role-desc') {
                    return list.sort((a, b) => normalize(b.dataset.role).localeCompare(normalize(a.dataset.role)));
                }

                return list;
            }

            function applyFilters() {
                const keyword = normalize(searchInput ? searchInput.value : '');

                let visible = rows.filter(function (row) {
                    const name = normalize(row.dataset.name);
                    const email = normalize(row.dataset.email);
                    const phone = normalize(row.dataset.phone);
                    const role = normalize(row.dataset.role);

                    const bySearch = keyword === '' || name.includes(keyword) || email.includes(keyword) || phone.includes(keyword);
                    const byRole = activeRole === 'all' || role === activeRole;

                    return bySearch && byRole;
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
                    activeRole = normalize(tab.dataset.role || 'all') || 'all';

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
</x-backoffice.layout>
