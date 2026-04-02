<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Menu Backoffice</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 p-6 text-slate-900">
    <main class="max-w-6xl mx-auto space-y-5">
        <header class="flex items-center justify-between bg-white border border-slate-200 rounded-2xl p-5">
            <div>
                <h1 class="text-2xl font-extrabold">Daftar Menu</h1>
                <p class="text-slate-500">Tampilan manajemen menu untuk backoffice.</p>
            </div>
            <a href="/backoffice/add_menu" class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded-xl transition">Tambah Menu</a>
        </header>

        <section class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-200 text-sm text-slate-600">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Harga</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3">Ayam Geprek</td>
                        <td class="px-4 py-3">makanan utama</td>
                        <td class="px-4 py-3">Rp 20.000</td>
                        <td class="px-4 py-3 text-sky-700 font-semibold">Edit</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3">Es Jeruk</td>
                        <td class="px-4 py-3">minuman</td>
                        <td class="px-4 py-3">Rp 12.000</td>
                        <td class="px-4 py-3 text-sky-700 font-semibold">Edit</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
