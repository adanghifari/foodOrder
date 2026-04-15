<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KedaiKlik - Menu</title>
    <link rel="icon" type="image/png" href="/images/KedaiKlikLogo.png">
    <link rel="apple-touch-icon" href="/images/KedaiKlikLogo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Menghilangkan scrollbar tapi fungsi scroll tetap ada */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-100 flex justify-center">

    <div class="w-full max-w-md bg-white min-h-screen shadow-2xl relative flex flex-col">
        
        <div class="p-6 pb-0">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-3xl font-extrabold text-gray-800">Menu</h1>
                <a href="/kedai/pembayaran/struk" class="inline-flex items-center gap-2 rounded-xl border border-[#C8641E] text-[#C8641E] bg-white px-3 py-2 text-sm font-bold hover:bg-orange-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17h6M9 13h6m-9 8h12a2 2 0 002-2V5a2 2 0 00-2-2H6a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <span>Lihat Struk</span>
                </a>
            </div>

            <div class="flex gap-3 overflow-x-auto no-scrollbar pb-4">
                @php
                    $tabs = [
                        ['name' => 'Semua', 'url' => 'menu'],
                        ['name' => 'Makanan utama', 'url' => 'menu/makanan-utama'],
                        ['name' => 'Cemilan', 'url' => 'menu/cemilan'],
                        ['name' => 'Minuman', 'url' => 'menu/minuman'],
                    ];
                @endphp

                @foreach($tabs as $tab)
                    <a href="/{{ $tab['url'] }}" 
                       class="whitespace-nowrap px-6 py-2.5 rounded-xl font-bold transition-all duration-300 shadow-sm border
                       {{ ($tab['url'] === 'menu' ? request()->is('menu') || request()->is('menu/semua') : request()->is($tab['url'])) ? 'bg-[#C8641E] text-white border-[#C8641E]' : 'bg-white text-gray-500 border-gray-100' }}">
                       {{ $tab['name'] }}
                    </a>
                @endforeach
            </div>

            <div class="relative mt-2 mb-6">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                </span>
                <input type="text" class="w-full bg-gray-100 border-none rounded-2xl py-4 pl-12 pr-4 outline-none focus:ring-2 focus:ring-[#C8641E]/50 placeholder-gray-400 font-medium" placeholder="Cari menu...">
            </div>
        </div>

        <div class="flex-1 px-6 space-y-5 overflow-y-auto pb-32">
            @foreach($menus as $menu)
            @php
                $menuStock = (int) ($menu['stock'] ?? 0);
                $isOutOfStock = $menuStock <= 0;
            @endphp
            <div class="flex bg-white rounded-[24px] p-3 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-50 items-center">
                <div class="w-28 h-28 flex-shrink-0">
                    @php
                        $rawImageUrl = $menu['image_url'] ?? '';
                        $imageSrc = str_starts_with($rawImageUrl, 'http://') || str_starts_with($rawImageUrl, 'https://') || str_starts_with($rawImageUrl, '/')
                            ? $rawImageUrl
                            : asset('images/' . $rawImageUrl);
                    @endphp
                    <img src="{{ $imageSrc }}" alt="{{ $menu['name'] }}" class="w-full h-full object-cover rounded-[20px]">
                </div>

                <div class="ml-4 flex-grow py-1">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="font-bold text-gray-800 text-lg leading-tight">{{ $menu['name'] }}</h3>
                        @if ($isOutOfStock)
                            <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-red-700">Habis</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">Stok {{ $menuStock }}</span>
                        @endif
                    </div>
                    <p class="text-[11px] text-gray-400 leading-snug mt-1 mb-2 line-clamp-2">{{ $menu['description'] }}</p>
                    <div class="flex justify-between items-center">
                        <span class="font-black text-gray-800 text-base">Rp {{ number_format($menu['price'], 0, ',', '.') }}</span>

                        <div class="flex items-center gap-2" data-menu-name="{{ $menu['name'] }}" data-menu-stock="{{ $menuStock }}">
                            <button onclick="kurangiDariKeranjang(this)"
                                    data-nama="{{ $menu['name'] }}"
                                    class="minus-btn hidden bg-[#C8641E]/20 text-[#C8641E] w-9 h-9 rounded-xl flex items-center justify-center font-bold transition active:scale-95">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M20 12H4" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                            </button>
                            <span class="qty-label hidden min-w-[20px] text-center font-bold text-gray-700">0</span>

                            <button onclick="tambahKeKeranjang(this)"
                                    data-id="{{ (string) $menu->_id }}"
                                    data-nama="{{ $menu['name'] }}"
                                    data-harga="{{ $menu['price'] }}"
                                    data-stock="{{ $menuStock }}"
                                    data-img="{{ $menu['image_url'] }}"
                                    data-desc="{{ $menu['description'] }}"
                                    {{ $isOutOfStock ? 'disabled' : '' }}
                                    class="{{ $isOutOfStock ? 'bg-slate-300 cursor-not-allowed shadow-none' : 'bg-[#C8641E] shadow-lg shadow-[#C8641E]/20 hover:scale-105' }} text-white p-2.5 rounded-xl transition active:scale-95">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div id="cart-cta" class="fixed bottom-6 left-1/2 -translate-x-1/2 w-[calc(100%-2rem)] max-w-[calc(28rem-2rem)] z-50 hidden">
            <a href="/keranjang" class="flex items-center justify-between bg-[#C8641E] text-white px-6 py-4 rounded-[22px] shadow-[0_20px_50px_rgba(200,100,30,0.3)] hover:bg-[#A85318] transition-all active:scale-[0.98]">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <div>
                        <span class="block font-bold text-lg leading-tight">Ke Keranjang</span>
                        <span id="cart-total" class="block text-xs font-semibold text-orange-100">Rp 0</span>
                    </div>
                </div>
                <span id="cart-badge" class="bg-white/20 backdrop-blur-md text-white px-4 py-1 rounded-full font-bold text-sm border border-white/30">
                    0 Item
                </span>
            </a>
        </div>

    </div>

    <script>
        let keranjang = JSON.parse(localStorage.getItem('kedaiKlikCart')) || [];

        function getQtyByName(nama) {
            const item = keranjang.find((cartItem) => cartItem.nama === nama);
            return item ? item.qty : 0;
        }

        function syncItemQtyControls() {
            document.querySelectorAll('[data-menu-name]').forEach((control) => {
                const nama = control.dataset.menuName;
                const qty = getQtyByName(nama);
                const minusButton = control.querySelector('.minus-btn');
                const qtyLabel = control.querySelector('.qty-label');

                if (!minusButton || !qtyLabel) {
                    return;
                }

                qtyLabel.textContent = qty;

                const hasItem = qty > 0;
                minusButton.classList.toggle('hidden', !hasItem);
                qtyLabel.classList.toggle('hidden', !hasItem);
            });
        }

        function updateBadge() {
            const totalItem = keranjang.reduce((sum, item) => sum + item.qty, 0);
            const totalPrice = keranjang.reduce((sum, item) => sum + ((Number(item.harga) || 0) * (Number(item.qty) || 0)), 0);
            document.getElementById('cart-badge').innerText = totalItem + " Item";
            document.getElementById('cart-total').innerText = "Rp " + totalPrice.toLocaleString('id-ID');

            const cartCta = document.getElementById('cart-cta');
            if (cartCta) {
                cartCta.classList.toggle('hidden', totalItem === 0);
            }
        }
        
        function tambahKeKeranjang(button) {
            if (button.disabled) {
                return;
            }

            const id = button.dataset.id;
            const nama = button.dataset.nama;
            const harga = button.dataset.harga;
            const stock = Number(button.dataset.stock || 0);
            const imageUrl = button.dataset.img;
            const description = button.dataset.desc;

            const index = keranjang.findIndex(item => item.nama === nama);
            const currentQty = index !== -1 ? Number(keranjang[index].qty || 0) : 0;

            if (stock <= 0 || currentQty >= stock) {
                return;
            }

            if (index !== -1) {
                keranjang[index].qty += 1;
            } else {
                keranjang.push({
                    id: id,
                    nama: nama,
                    harga: harga,
                    stock: stock,
                    img: imageUrl,
                    desc: description || 'Deskripsi tidak tersedia',
                    qty: 1
                });
            }

            localStorage.setItem('kedaiKlikCart', JSON.stringify(keranjang));
            updateBadge();
            syncItemQtyControls();
        }

        function kurangiDariKeranjang(button) {
            const nama = button.dataset.nama;
            const index = keranjang.findIndex(item => item.nama === nama);

            if (index === -1) {
                return;
            }

            keranjang[index].qty -= 1;

            if (keranjang[index].qty <= 0) {
                keranjang.splice(index, 1);
            }

            localStorage.setItem('kedaiKlikCart', JSON.stringify(keranjang));
            updateBadge();
            syncItemQtyControls();
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateBadge();
            syncItemQtyControls();
        });
    </script>
</body>
</html>
