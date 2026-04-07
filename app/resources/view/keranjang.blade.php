<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KedaiKlik - Pesanan Saya</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex justify-center">

    <div class="w-full max-w-md bg-white min-h-screen shadow-2xl relative flex flex-col p-6">
        
        <div class="flex items-center mb-8">
            <a href="/menu/hidangan" class="p-2 -ml-2">
                <svg class="w-6 h-6 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="flex-grow text-center text-2xl font-bold text-gray-800 mr-8">Pesanan saya</h1>
        </div>

        <div id="cart-items-container" class="space-y-6 mb-8 overflow-y-auto max-h-[40vh] no-scrollbar">
            </div>

        <div class="space-y-4 mb-8">
            <div>
                <label class="block text-gray-700 font-bold mb-2">Email</label>
                <input type="email" placeholder="Email" class="w-full bg-white border border-gray-200 rounded-xl py-3 px-4 outline-none focus:ring-2 focus:ring-[#C8641E]/30 transition">
            </div>
            <div>
                <label class="block text-gray-700 font-bold mb-2">Nama Pemesan</label>
                <input type="text" placeholder="Nama Pemesan" class="w-full bg-white border border-gray-200 rounded-xl py-3 px-4 outline-none focus:ring-2 focus:ring-[#C8641E]/30 transition">
            </div>
        </div>

        <div class="mb-10">
            <h2 class="font-bold text-gray-800 mb-3 text-lg">Detail Pembayaran</h2>
            <div class="space-y-2">
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal</span>
                    <span id="subtotal" class="font-bold">Rp 0</span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Biaya Layanan</span>
                    <span id="service-fee" class="font-bold">Rp 5.000</span>
                </div>
                <div class="flex justify-between text-gray-800 text-lg border-t border-gray-100 pt-2 mt-2">
                    <span class="font-bold">Total Pembayaran</span>
                    <span id="total-payment" class="font-bold text-[#C8641E]">Rp 0</span>
                </div>
            </div>
        </div>

        <div class="flex gap-4 mt-auto pb-4">
            <a href="/menu/hidangan" class="flex-1 text-center py-4 rounded-xl border-2 border-[#C8641E] text-[#C8641E] font-bold hover:bg-orange-50 transition">
                Tambah Item
            </a>
            <button onclick="prosesBayar()" class="flex-1 py-4 rounded-xl bg-[#C19A6B] text-white font-bold shadow-lg hover:bg-[#b0895a] transition">
                Bayar
            </button>
        </div>

    </div>

    <script>
        // Fungsi untuk merender item dari localStorage
       function renderCart() {
    const container = document.getElementById('cart-items-container');
    const cart = JSON.parse(localStorage.getItem('kedaiKlikCart')) || [];
    
    if (cart.length === 0) {
        container.innerHTML = `
            <div class="text-center py-10">
                <p class="text-gray-400">Keranjang kamu kosong nih...</p>
            </div>
        `;
        updateTotals(0);
        return;
    }

    container.innerHTML = cart.map((item, index) => `
        <div class="flex items-start gap-4 animate-fadeIn">
            <div class="w-24 h-20 flex-shrink-0">
                <img src="/images/${encodeURIComponent(item.img)}" class="w-full h-full object-cover rounded-xl shadow-sm" onerror="this.src='/images/default.jpg'">
            </div>
            <div class="flex-grow">
                <h3 class="font-bold text-gray-800">${item.nama}</h3>
                <p class="text-[10px] text-gray-400 leading-tight mb-2">${item.desc || 'Deskripsi tidak tersedia'}</p>
                <div class="flex justify-between items-center">
                    <span class="font-bold text-gray-800">
                        Rp ${((item.harga || 0) * item.qty).toLocaleString('id-ID')}
                    </span>
                    <div class="flex items-center gap-3">
                        <button onclick="changeQty(${index}, -1)" class="bg-[#C8641E]/20 text-[#C8641E] w-7 h-7 rounded-lg flex items-center justify-center font-bold transition active:scale-90">-</button>
                        <span class="font-bold text-gray-800">${item.qty}</span>
                        <button onclick="changeQty(${index}, 1)" class="bg-[#C8641E] text-white w-7 h-7 rounded-lg flex items-center justify-center font-bold transition active:scale-90">+</button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');

    calculateSubtotal(cart);
}

        function changeQty(index, delta) {
            let cart = JSON.parse(localStorage.getItem('kedaiKlikCart')) || [];
            cart[index].qty += delta;

            if (cart[index].qty <= 0) {
                cart.splice(index, 1); // Hapus jika 0
            }

            localStorage.setItem('kedaiKlikCart', JSON.stringify(cart));
            renderCart();
        }

       function calculateSubtotal(cart) {
    // Pastikan menggunakan item.harga agar tidak muncul NaN
    const subtotal = cart.reduce((sum, item) => sum + ((item.harga || 0) * item.qty), 0);
    updateTotals(subtotal);
}

        function updateTotals(subtotal) {
            const serviceFee = subtotal > 0 ? 5000 : 0;
            const total = subtotal + serviceFee;

            document.getElementById('subtotal').innerText = "Rp " + subtotal.toLocaleString('id-ID');
            document.getElementById('service-fee').innerText = "Rp " + serviceFee.toLocaleString('id-ID');
            document.getElementById('total-payment').innerText = "Rp " + total.toLocaleString('id-ID');
        }

        function prosesBayar() {
            const cart = JSON.parse(localStorage.getItem('kedaiKlikCart')) || [];
            if (cart.length === 0) {
                const message = document.createElement('div');
                message.className = 'fixed top-4 left-1/2 -translate-x-1/2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-700 shadow-lg';
                message.textContent = 'Pilih menu dulu ya!';
                document.body.appendChild(message);
                window.setTimeout(function () {
                    message.remove();
                }, 1800);
                return;
            }
            const message = document.createElement('div');
            message.className = 'fixed top-4 left-1/2 -translate-x-1/2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700 shadow-lg';
            message.textContent = 'Terima kasih! Pesanan kamu sedang diproses.';
            document.body.appendChild(message);
            localStorage.removeItem('kedaiKlikCart'); // Kosongkan keranjang setelah bayar
            window.setTimeout(function () {
                window.location.href = '/menu/hidangan';
            }, 1200);
        }

        // Jalankan saat halaman dimuat
        document.addEventListener('DOMContentLoaded', renderCart);
    </script>

    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .animate-fadeIn { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</body>
</html>
