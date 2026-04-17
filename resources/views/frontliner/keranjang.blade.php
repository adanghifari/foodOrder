<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>KedaiKlik - Pesanan Saya</title>
    <link rel="icon" type="image/png" href="/images/KedaiKlikLogo.png">
    <link rel="apple-touch-icon" href="/images/KedaiKlikLogo.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex justify-center">

    <div class="w-full max-w-md bg-white min-h-screen shadow-2xl relative flex flex-col p-6">
        
        <div class="flex items-center mb-8">
            <a href="/menu" class="p-2 -ml-2">
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
                <label class="block text-gray-700 font-bold mb-2">Nomor Meja</label>
                <input id="table-number-input" type="text" value="{{ $tableNumber ?? '-' }}" readonly class="w-full bg-gray-100 border border-gray-200 rounded-xl py-3 px-4 text-gray-600 outline-none cursor-not-allowed">
            </div>
            <div>
                <label class="block text-gray-700 font-bold mb-2">Email</label>
                <input id="email-input" type="email" placeholder="Email" class="w-full bg-white border border-gray-200 rounded-xl py-3 px-4 outline-none focus:ring-2 focus:ring-[#C8641E]/30 transition">
                <p id="email-error" class="text-red-500 text-xs mt-1 hidden">Email wajib diisi dengan format yang valid.</p>
            </div>
            <div>
                <label class="block text-gray-700 font-bold mb-2">Nama Pemesan</label>
                <input id="customer-name-input" type="text" placeholder="Nama Pemesan" class="w-full bg-white border border-gray-200 rounded-xl py-3 px-4 outline-none focus:ring-2 focus:ring-[#C8641E]/30 transition">
                <p id="customer-name-error" class="text-red-500 text-xs mt-1 hidden">Nama pemesan wajib diisi.</p>
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
            <a href="/menu" class="flex-1 text-center py-4 rounded-xl border-2 border-[#C8641E] text-[#C8641E] font-bold hover:bg-orange-50 transition">
                Tambah Item
            </a>
            <button id="bayar-button" onclick="prosesBayar()" class="flex-1 py-4 rounded-xl bg-[#C8641E] text-white font-bold shadow-lg hover:bg-[#A85318] transition">
                Bayar
            </button>
        </div>

    </div>

    <x-notification-center />

    <script>
        // Fungsi untuk merender item dari localStorage
       function resolveCartImageSrc(item) {
    const raw = item.img || item.image_url || '';

    if (!raw) {
        return '/images/esteh.jpg';
    }

    if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('/')) {
        return raw;
    }

    return `/images/${encodeURIComponent(raw)}`;
}

       function renderCart() {
    const container = document.getElementById('cart-items-container');
    const cart = JSON.parse(localStorage.getItem('kedaiKlikCart')) || [];
    
    if (cart.length === 0) {
        window.location.href = '/menu';
        return;
    }

    container.innerHTML = cart.map((item, index) => `
        <div class="flex items-start gap-4 animate-fadeIn">
            <div class="w-24 h-20 flex-shrink-0">
                <img src="${resolveCartImageSrc(item)}" class="w-full h-full object-cover rounded-xl shadow-sm" onerror="this.src='/images/esteh.jpg'">
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
            const item = cart[index];

            if (!item) {
                return;
            }

            const stock = Number(item.stock || 0);

            if (delta > 0 && stock > 0 && Number(item.qty || 0) >= stock) {
                showNotification({
                    type: 'warning',
                    title: 'Stok tidak cukup',
                    message: `Stok untuk ${item.nama} tersisa ${stock}.`,
                });
                return;
            }

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

        function showNotification(options) {
            if (window.KedaiKlikNotify && typeof window.KedaiKlikNotify.show === 'function') {
                window.KedaiKlikNotify.show(options);
                return;
            }
        }

        async function showTableRequiredPopup() {
            if (window.KedaiKlikNotify && typeof window.KedaiKlikNotify.confirm === 'function') {
                await window.KedaiKlikNotify.confirm({
                    type: 'warning',
                    badge: 'Perhatian',
                    title: 'Nomor meja belum ada',
                    message: 'Silahkan order dengan scan qr code pada meja terlebih dahulu',
                    confirmText: 'Oke',
                    singleButton: true,
                });
                return;
            }

            window.alert('Silahkan order dengan scan qr code pada meja terlebih dahulu');
        }

        function validateCustomerInfo() {
            const emailInput = document.getElementById('email-input');
            const nameInput = document.getElementById('customer-name-input');
            const emailError = document.getElementById('email-error');
            const nameError = document.getElementById('customer-name-error');

            const email = (emailInput?.value || '').trim();
            const customerName = (nameInput?.value || '').trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            const isEmailValid = emailRegex.test(email);
            const isNameValid = customerName.length > 0;

            emailError?.classList.toggle('hidden', isEmailValid);
            nameError?.classList.toggle('hidden', isNameValid);

            if (emailInput) {
                emailInput.classList.toggle('border-red-400', !isEmailValid);
            }

            if (nameInput) {
                nameInput.classList.toggle('border-red-400', !isNameValid);
            }

            return {
                valid: isEmailValid && isNameValid,
                email,
                customerName,
            };
        }

        async function prosesBayar() {
            const cart = JSON.parse(localStorage.getItem('kedaiKlikCart')) || [];
            const tableNumber = (document.getElementById('table-number-input')?.value || '').trim();
            const payButton = document.getElementById('bayar-button');

            if (cart.length === 0) {
                showNotification({ type: 'warning', title: 'Keranjang kosong', message: 'Pilih menu dulu ya!' });
                return;
            }

            if (!tableNumber || tableNumber === '-') {
                await showTableRequiredPopup();
                return;
            }

            const customerInfo = validateCustomerInfo();
            if (!customerInfo.valid) {
                showNotification({ type: 'warning', title: 'Data belum lengkap', message: 'Lengkapi nama dan email yang valid dulu ya.' });
                return;
            }

            const condensedItems = cart.map(function (item) {
                return {
                    menuId: String(item.id || ''),
                    qty: Number(item.qty || 0),
                };
            });

            const invalidItem = condensedItems.find(function (item) {
                return item.menuId === '' || item.qty <= 0;
            });

            if (invalidItem) {
                showNotification({
                    type: 'error',
                    title: 'Item tidak valid',
                    message: 'Ada item keranjang yang tidak valid. Silakan kembali ke menu dan pilih ulang item.',
                });
                return;
            }

            const subtotal = cart.reduce((sum, item) => sum + ((item.harga || 0) * item.qty), 0);
            const serviceFee = subtotal > 0 ? 5000 : 0;
            const totalPayment = subtotal + serviceFee;

            // Temporary payload for backoffice integration until checkout API is wired from web flow.
            localStorage.setItem('kedaiKlikCustomerInfo', JSON.stringify({
                name: customerInfo.customerName,
                email: customerInfo.email,
            }));

            localStorage.setItem('kedaiKlikLastCheckout', JSON.stringify({
                tableNumber,
                customerName: customerInfo.customerName,
                customerEmail: customerInfo.email,
                items: cart,
                subtotal,
                serviceFee,
                totalPayment,
                createdAt: new Date().toISOString(),
            }));

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            try {
                if (payButton) {
                    payButton.disabled = true;
                    payButton.classList.add('opacity-70', 'cursor-not-allowed');
                    payButton.textContent = 'Memproses...';
                }

                const response = await fetch('/kedai/pembayaran/create', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        tableNumber: Number(tableNumber),
                        customerName: customerInfo.customerName,
                        customerEmail: customerInfo.email,
                        items: condensedItems,
                    }),
                });

                const result = await response.json();

                if (!response.ok || result?.status !== 'success') {
                    throw new Error(result?.message || 'Gagal membuat transaksi pembayaran.');
                }

                const redirectUrl = result?.data?.redirect_url || '';
                const orderId = result?.data?.order_id || '';
                const midtransOrderId = result?.data?.midtrans_order_id || '';

                localStorage.setItem('kedaiKlikLastCheckout', JSON.stringify({
                    tableNumber,
                    customerName: customerInfo.customerName,
                    customerEmail: customerInfo.email,
                    items: cart,
                    subtotal,
                    serviceFee,
                    totalPayment,
                    orderId,
                    midtransOrderId,
                    createdAt: new Date().toISOString(),
                }));

                if (!redirectUrl) {
                    throw new Error('URL pembayaran Midtrans tidak tersedia.');
                }

                // Payment has been successfully triggered on backend; reset cart for next order cycle.
                localStorage.removeItem('kedaiKlikCart');

                window.location.href = redirectUrl;
            } catch (error) {
                showNotification({
                    type: 'error',
                    title: 'Pembayaran gagal',
                    message: error?.message || 'Terjadi kesalahan saat memproses pembayaran.',
                });

                if (payButton) {
                    payButton.disabled = false;
                    payButton.classList.remove('opacity-70', 'cursor-not-allowed');
                    payButton.textContent = 'Bayar';
                }
            }
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
