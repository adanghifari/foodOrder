<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KedaiKlik Frontliner</title>
    <link rel="icon" type="image/png" href="/images/KedaiKlikLogo.png">
    <link rel="apple-touch-icon" href="/images/KedaiKlikLogo.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-b from-orange-50 to-white text-slate-800">
    <main class="max-w-4xl mx-auto px-6 py-10">
        <section class="bg-white border border-orange-100 shadow-xl rounded-3xl p-8">
            <p class="text-sm font-bold tracking-wide text-orange-600 mb-2">KEDAIKLIK FRONTLINER</p>
            <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900">Operasional Meja & Pesanan Customer</h1>
            <p class="text-slate-600 mt-3 max-w-2xl">Versi Blade Laravel ini disusun untuk alur customer dine-in berbasis QR scan. Semua menu tetap ambil data dari backend project ini.</p>

            <div class="grid sm:grid-cols-2 gap-4 mt-8">
                <a href="/menu" class="rounded-2xl bg-orange-600 hover:bg-orange-700 text-white font-bold py-4 px-5 text-center transition">Buka Halaman Menu</a>
                <a href="/keranjang" class="rounded-2xl bg-slate-900 hover:bg-slate-950 text-white font-bold py-4 px-5 text-center transition">Buka Keranjang</a>
                <a href="/scan?tableId=2" class="rounded-2xl border-2 border-orange-300 hover:border-orange-400 text-orange-700 font-bold py-4 px-5 text-center transition">Simulasi Scan QR Meja 2</a>
                <a href="/backoffice" class="rounded-2xl border-2 border-slate-300 hover:border-slate-400 text-slate-700 font-bold py-4 px-5 text-center transition">Masuk Area Backoffice</a>
            </div>
        </section>
    </main>
</body>
</html>
