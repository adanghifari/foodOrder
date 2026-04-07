<aside id="bo-sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-[#FCB861] text-[#6A2B09] border-r border-[#6A2B09]/25 transform -translate-x-full transition-transform duration-300 lg:translate-x-0 flex flex-col shrink-0 overflow-hidden">
    <div class="h-16 px-4 border-b border-[#6A2B09]/25 flex items-center">
        <a href="/backoffice/dashboard" class="flex items-center gap-2 min-w-0">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-[#6A2B09] text-[#FCB861] font-extrabold">K</span>
            <div class="sidebar-logo-text min-w-0">
                <p class="font-extrabold leading-tight">KedaiKlik</p>
                <p class="text-[11px] text-[#6A2B09]/80">Backoffice</p>
            </div>
        </a>
    </div>

    <nav class="px-3 py-4 space-y-1.5 flex-1">
        <a href="/backoffice/dashboard" class="sidebar-nav-link flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->is('backoffice/dashboard') ? 'bg-[#6A2B09] text-[#FCB861]' : 'text-[#6A2B09] hover:bg-white/45' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="sidebar-icon w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5 12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V9.5Z"/></svg>
            <span class="sidebar-label">Dashboard</span>
        </a>

        <a href="/backoffice/overview" class="sidebar-nav-link flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition text-[#6A2B09] hover:bg-white/45">
            <svg xmlns="http://www.w3.org/2000/svg" class="sidebar-icon w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h10M7 16h6"/></svg>
            <span class="sidebar-label">Overview</span>
        </a>

        <a href="/backoffice/daftar_menu" class="sidebar-nav-link flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->is('backoffice/daftar_menu') || request()->is('backoffice/daftar_menu/*') || request()->is('backoffice/add_menu') ? 'bg-[#6A2B09] text-[#FCB861]' : 'text-[#6A2B09] hover:bg-white/45' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="sidebar-icon w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
            <span class="sidebar-label">Kelola Menu</span>
        </a>

        <a href="/backoffice/daftar_pesanan" class="sidebar-nav-link flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->is('backoffice/daftar_pesanan') ? 'bg-[#6A2B09] text-[#FCB861]' : 'text-[#6A2B09] hover:bg-white/45' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="sidebar-icon w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h18M6 12h12M8 17h8"/></svg>
            <span class="sidebar-label">Kelola Pesanan</span>
        </a>

        <a href="/backoffice/kelola_meja" class="sidebar-nav-link flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition text-[#6A2B09] hover:bg-white/45">
            <svg xmlns="http://www.w3.org/2000/svg" class="sidebar-icon w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 10h16M10 4v16"/></svg>
            <span class="sidebar-label">Kelola Meja</span>
        </a>

        <a href="/backoffice/pembayaran" class="sidebar-nav-link flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->is('backoffice/pembayaran') ? 'bg-[#6A2B09] text-[#FCB861]' : 'text-[#6A2B09] hover:bg-white/45' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="sidebar-icon w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
            <span class="sidebar-label">Pembayaran</span>
        </a>

        <a href="/backoffice/pengguna" class="sidebar-nav-link flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->is('backoffice/pengguna') ? 'bg-[#6A2B09] text-[#FCB861]' : 'text-[#6A2B09] hover:bg-white/45' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="sidebar-icon w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg>
            <span class="sidebar-label">Pengguna</span>
        </a>
    </nav>

    <div class="p-3 border-t border-[#6A2B09]/25">
        <div class="relative">
            <button id="bo-profile-toggle" type="button" class="w-full sidebar-nav-link flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-[#6A2B09] hover:bg-white/45 transition">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-[#6A2B09] text-[#FCB861] font-black">A</span>
                <span class="sidebar-profile-name">Administrator</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="sidebar-label w-4 h-4 ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            </button>

            <div id="bo-profile-menu" class="hidden absolute bottom-14 left-0 right-0 rounded-xl border border-[#6A2B09]/25 bg-white shadow-lg overflow-hidden">
                <button id="bo-logout-btn" type="button" class="w-full text-left px-3.5 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50 transition">Logout</button>
            </div>
        </div>
    </div>
</aside>
