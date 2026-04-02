<x-backoffice.layout pageTitle="Dashboard" pageSubtitle="Halo Admin!, Selamat Datang Kembali">
    <section class="grid grid-cols-1 xl:grid-cols-12 gap-5">
        <div class="xl:col-span-8 space-y-5">
            <article id="overview-panel" class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-lg md:text-xl font-extrabold text-[var(--rich-black)]">Pesanan Aktif</h2>
                </div>

                <div class="space-y-3">
                    <div class="rounded-xl border border-slate-200 hover:border-[var(--alloy-orange)] hover:shadow-md transition p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold text-slate-500">Order ID</p>
                                <p class="text-sm font-extrabold text-[var(--rich-black)]">ORD-240301</p>
                            </div>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">Pending</span>
                        </div>
                        <ul class="mt-3 space-y-1 text-sm text-slate-700">
                            <li>Nasi Goreng Seafood (1)</li>
                            <li>Es Teh Manis (2)</li>
                        </ul>
                        <button class="mt-4 inline-flex items-center rounded-lg bg-[var(--alloy-orange)] hover:bg-[var(--philippine-bronze)] text-white text-sm font-bold px-3.5 py-2 transition">Update Status</button>
                    </div>

                    <div class="rounded-xl border border-slate-200 hover:border-[var(--alloy-orange)] hover:shadow-md transition p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold text-slate-500">Order ID</p>
                                <p class="text-sm font-extrabold text-[var(--rich-black)]">ORD-240302</p>
                            </div>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-orange-100 text-orange-700">Cooking</span>
                        </div>
                        <ul class="mt-3 space-y-1 text-sm text-slate-700">
                            <li>Ayam Bakar Madu (2)</li>
                            <li>Lemon Tea (1)</li>
                        </ul>
                        <button class="mt-4 inline-flex items-center rounded-lg bg-[var(--alloy-orange)] hover:bg-[var(--philippine-bronze)] text-white text-sm font-bold px-3.5 py-2 transition">Update Status</button>
                    </div>

                    <div class="rounded-xl border border-slate-200 hover:border-[var(--alloy-orange)] hover:shadow-md transition p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold text-slate-500">Order ID</p>
                                <p class="text-sm font-extrabold text-[var(--rich-black)]">ORD-240303</p>
                            </div>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">Ready</span>
                        </div>
                        <ul class="mt-3 space-y-1 text-sm text-slate-700">
                            <li>Mie Goreng Jawa (1)</li>
                            <li>Teh Tarik (1)</li>
                            <li>Tempe Mendoan (1)</li>
                        </ul>
                        <button class="mt-4 inline-flex items-center rounded-lg bg-[var(--alloy-orange)] hover:bg-[var(--philippine-bronze)] text-white text-sm font-bold px-3.5 py-2 transition">Update Status</button>
                    </div>
                </div>
            </article>
        </div>

        <div id="user-panel" class="xl:col-span-4 space-y-5">
            <article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
                <h2 class="text-lg font-extrabold text-[var(--rich-black)] mb-4">Aksi Cepat</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 xl:grid-cols-1 gap-3">
                    <a href="/backoffice/add_menu" class="text-center rounded-xl bg-[var(--alloy-orange)] hover:bg-[var(--philippine-bronze)] text-white font-bold py-3 transition shadow-sm">Tambah Menu</a>
                    <a href="/backoffice/daftar_menu" class="text-center rounded-xl bg-[var(--rajah)] hover:brightness-95 text-[var(--philippine-bronze)] font-bold py-3 transition shadow-sm">Kelola Menu</a>
                    <a href="/backoffice/daftar_pesanan" class="text-center rounded-xl bg-[var(--auro-metal-saurus)] hover:brightness-95 text-white font-bold py-3 transition shadow-sm">Lihat Pesanan</a>
                </div>
            </article>

            <article id="payment-panel" class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
                <h2 class="text-lg font-extrabold text-[var(--rich-black)] mb-4">Notifikasi</h2>
                <div class="space-y-2.5">
                    <div class="rounded-xl border border-yellow-200 bg-yellow-50 px-3.5 py-3 text-sm text-yellow-800 font-semibold">5 pesanan belum diproses</div>
                    <div class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-800 font-semibold">2 menu sedang habis</div>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
                <h2 class="text-lg font-extrabold text-[var(--rich-black)] mb-4">Aktivitas Terbaru</h2>
                <ul class="space-y-2.5 text-sm text-slate-700">
                    <li class="rounded-lg bg-slate-50 px-3 py-2">Menu "Nasi Goreng Seafood" diperbarui</li>
                    <li class="rounded-lg bg-slate-50 px-3 py-2">Pesanan ORD-240287 selesai</li>
                    <li class="rounded-lg bg-slate-50 px-3 py-2">Status ORD-240301 diubah ke Pending</li>
                </ul>
            </article>
        </div>
    </section>
</x-backoffice.layout>
