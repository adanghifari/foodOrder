<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KedaiKlik - Menu</title>
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
            <h1 class="text-3xl font-extrabold text-gray-800 mb-6">Menu</h1>

            <div class="flex gap-3 overflow-x-auto no-scrollbar pb-4">
                @php
                    $tabs = [
                        ['name' => 'Hidangan', 'url' => 'menu/hidangan'],
                        ['name' => 'Cemilan', 'url' => 'menu/cemilan'],
                        ['name' => 'Minuman', 'url' => 'menu/minuman'],
                    ];
                @endphp

                @foreach($tabs as $tab)
                    <a href="/{{ $tab['url'] }}" 
                       class="whitespace-nowrap px-6 py-2.5 rounded-xl font-bold transition-all duration-300 shadow-sm border
                       {{ request()->is($tab['url']) ? 'bg-[#C8641E] text-white border-[#C8641E]' : 'bg-white text-gray-500 border-gray-100' }}">
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
            <div class="flex bg-white rounded-[24px] p-3 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-50 items-center">
                <div class="w-28 h-28 flex-shrink-0">
                    <img src="{{ asset('images/' . $menu['image_url']) }}" alt="{{ $menu['name'] }}" class="w-full h-full object-cover rounded-[20px]">
                </div>

                <div class="ml-4 flex-grow py-1">
                    <h3 class="font-bold text-gray-800 text-lg leading-tight">{{ $menu['name'] }}</h3>
                    <p class="text-[11px] text-gray-400 leading-snug mt-1 mb-2 line-clamp-2">{{ $menu['description'] }}</p>
                    <div class="flex justify-between items-center">
                        <span class="font-black text-gray-800 text-base">Rp {{ number_format($menu['price'], 0, ',', '.') }}</span>
                        
                        <button onclick="tambahKeKeranjang(this)"
                                data-nama="{{ $menu['name'] }}"
                                data-harga="{{ $menu['price'] }}"
                                data-img="{{ $menu['image_url'] }}"
                                data-desc="{{ $menu['description'] }}"
                                class="bg-[#C8641E] text-white p-2.5 rounded-xl shadow-lg shadow-[#C8641E]/20 hover:scale-105 transition active:scale-95">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="absolute bottom-8 left-0 right-0 px-6 z-50">
            <a href="/keranjang" class="flex items-center justify-between bg-[#C8641E] text-white px-6 py-4 rounded-[22px] shadow-[0_20px_50px_rgba(200,100,30,0.3)] hover:bg-[#A85318] transition-all active:scale-[0.98]">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <span class="font-bold text-lg">Ke Keranjang</span>
                </div>
                <span id="cart-badge" class="bg-white/20 backdrop-blur-md text-white px-4 py-1 rounded-full font-bold text-sm border border-white/30">
                    0 Item
                </span>
            </a>
        </div>

    </div>

    <script>
        let keranjang = JSON.parse(localStorage.getItem('kedaiKlikCart')) || [];

        function updateBadge() {
            const totalItem = keranjang.reduce((sum, item) => sum + item.qty, 0);
            document.getElementById('cart-badge').innerText = totalItem + " Item";
        }
        
        function tambahKeKeranjang(button) {
            const nama = button.dataset.nama;
            const harga = button.dataset.harga;
            const imageUrl = button.dataset.img;
            const description = button.dataset.desc;
            
            let keranjang = JSON.parse(localStorage.getItem('kedaiKlikCart')) || [];
            const index = keranjang.findIndex(item => item.nama === nama);

            if (index !== -1) {
                keranjang[index].qty += 1;
            } else {
                keranjang.push({
                    nama: nama,
                    harga: harga,
                    img: imageUrl,
                    desc: description || 'Deskripsi tidak tersedia',
                    qty: 1
                });
            }

            localStorage.setItem('kedaiKlikCart', JSON.stringify(keranjang));
            updateBadge();
        }

        document.addEventListener('DOMContentLoaded', updateBadge);
    </script>
</body>
</html>