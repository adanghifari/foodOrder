<x-backoffice.layout pageTitle="Kelola Menu">
    <section class="space-y-5">
        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
            <div>
                <h2 class="text-xl md:text-2xl font-extrabold text-[var(--rich-black)]">Daftar Menu</h2>
            </div>

            <div class="mt-4 grid grid-cols-1 lg:grid-cols-12 gap-3">
                <div class="lg:col-span-8 space-y-3">
                    <input id="menu-search" type="text" placeholder="Cari menu..." class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[var(--rajah)]/70 focus:border-[var(--rajah)]">

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="menu-category-tab inline-flex items-center rounded-xl border border-[#6A2B09] bg-[#6A2B09] text-[#FCB861] text-xs font-bold px-3 py-2 transition" data-category="all">Semua</button>
                        <button type="button" class="menu-category-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-category="makanan utama">Makanan Utama</button>
                        <button type="button" class="menu-category-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-category="cemilan">Cemilan</button>
                        <button type="button" class="menu-category-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-category="minuman">Minuman</button>
                    </div>
                </div>

                <div class="lg:col-span-4">
                    <select id="menu-sort" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[var(--rajah)]/70 focus:border-[var(--rajah)]">
                        <option value="default">Sort by: Default</option>
                        <option value="name-asc">Nama A-Z</option>
                        <option value="name-desc">Nama Z-A</option>
                        <option value="price-asc">Harga Termurah</option>
                        <option value="price-desc">Harga Termahal</option>
                        <option value="stock-asc">Stok Terendah</option>
                        <option value="stock-desc">Stok Tertinggi</option>
                    </select>

                    <div class="mt-3 sticky top-24 z-20">
                        <a href="/backoffice/daftar_menu?create=1" class="w-full inline-flex items-center justify-center rounded-2xl bg-[var(--alloy-orange)] hover:bg-[var(--philippine-bronze)] text-white text-base font-extrabold px-6 py-3.5 transition shadow-xl">Tambah Menu</a>
                    </div>
                </div>
            </div>
        </article>

        <section id="menu-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @forelse ($menus as $menu)
                @php
                    $stock = (int) ($menu->stock ?? 0);
                    $stockClass = $stock <= 0
                        ? 'bg-red-100 text-red-700'
                        : ($stock <= 10 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700');
                    $imageUrl = $menu->image_url ?: 'https://placehold.co/900x600/f3f4f6/64748b?text=No+Image';
                @endphp
                <article
                    class="menu-card rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden"
                    data-name="{{ strtolower($menu->name) }}"
                    data-category="{{ strtolower($menu->category ?? '') }}"
                    data-price="{{ (float) ($menu->price ?? 0) }}"
                    data-stock="{{ $stock }}"
                >
                    <img src="{{ $imageUrl }}" alt="{{ $menu->name }}" class="h-44 w-full object-cover">
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-extrabold text-[var(--rich-black)]">{{ $menu->name }}</h3>
                                <p class="text-xs font-semibold text-slate-500">{{ $menu->category }}</p>
                            </div>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full {{ $stockClass }}">Stok {{ $stock }}</span>
                        </div>
                        <p class="text-sm font-semibold text-[var(--philippine-bronze)]">Rp {{ number_format((float) $menu->price, 0, ',', '.') }}</p>
                        <div class="flex items-center gap-2">
                            <a href="/backoffice/daftar_menu?edit={{ urlencode((string) $menu->_id) }}" class="inline-flex items-center rounded-lg bg-[var(--rajah)] hover:brightness-95 text-[var(--philippine-bronze)] text-sm font-bold px-3.5 py-2 transition">Edit Menu</a>
                            <a href="/backoffice/daftar_menu?detail={{ urlencode((string) $menu->_id) }}" class="ml-auto inline-flex items-center rounded-xl border border-[#2563EB] bg-white hover:bg-blue-50 text-[#2563EB] text-sm font-bold px-5 py-2.5 transition">See Detail</a>
                        </div>
                    </div>
                </article>
            @empty
                <article class="md:col-span-2 xl:col-span-3 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                    <p class="text-sm font-semibold text-slate-500">Belum ada data menu.</p>
                </article>
            @endforelse

            <article id="menu-filter-empty" class="hidden md:col-span-2 xl:col-span-3 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                <p class="text-sm font-semibold text-slate-500">Menu tidak ditemukan untuk filter ini.</p>
            </article>
        </section>

        @if (!empty($selectedMenu))
            @include('backoffice.menu.detail.detail', ['menu' => $selectedMenu])
        @endif

        @if (!empty($selectedEditMenu))
            @include('backoffice.menu.edit.edit', ['menu' => $selectedEditMenu, 'allowedCategories' => $allowedCategories])
        @endif

        @if (!empty($showCreateModal))
            @include('backoffice.menu.create.create', ['allowedCategories' => $allowedCategories])
        @endif

    </section>

    <script>
        (function () {
            const grid = document.getElementById('menu-grid');
            const searchInput = document.getElementById('menu-search');
            const sortSelect = document.getElementById('menu-sort');
            const tabs = Array.from(document.querySelectorAll('.menu-category-tab'));
            const emptyState = document.getElementById('menu-filter-empty');

            if (!grid) {
                return;
            }

            const cards = Array.from(grid.querySelectorAll('.menu-card'));
            if (cards.length === 0) {
                return;
            }

            const baseOrder = new Map(cards.map((card, index) => [card, index]));
            let activeCategory = 'all';

            function normalize(text) {
                return String(text || '').toLowerCase().trim();
            }

            function sortCards(list) {
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

                if (mode === 'price-asc') {
                    return list.sort((a, b) => Number(a.dataset.price || 0) - Number(b.dataset.price || 0));
                }

                if (mode === 'price-desc') {
                    return list.sort((a, b) => Number(b.dataset.price || 0) - Number(a.dataset.price || 0));
                }

                if (mode === 'stock-asc') {
                    return list.sort((a, b) => Number(a.dataset.stock || 0) - Number(b.dataset.stock || 0));
                }

                if (mode === 'stock-desc') {
                    return list.sort((a, b) => Number(b.dataset.stock || 0) - Number(a.dataset.stock || 0));
                }

                return list;
            }

            function applyFilters() {
                const keyword = normalize(searchInput ? searchInput.value : '');

                let visible = cards.filter(function (card) {
                    const name = normalize(card.dataset.name);
                    const category = normalize(card.dataset.category);
                    const bySearch = keyword === '' || name.includes(keyword);
                    const byCategory = activeCategory === 'all' || category === activeCategory;

                    return bySearch && byCategory;
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
                    activeCategory = normalize(tab.dataset.category || 'all') || 'all';

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
