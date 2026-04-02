<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Menu Backoffice</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 p-6 text-slate-900">
    <main class="max-w-2xl mx-auto bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
        <h1 class="text-2xl font-extrabold mb-5">Tambah Menu</h1>
        <form class="space-y-4" onsubmit="event.preventDefault(); alert('Form contoh Blade berhasil disubmit. Integrasikan ke endpoint admin menu untuk produksi.');">
            <div>
                <label class="block text-sm font-semibold mb-1">Nama Menu</label>
                <input type="text" class="w-full border border-slate-300 rounded-xl px-4 py-3" required>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Kategori</label>
                <select class="w-full border border-slate-300 rounded-xl px-4 py-3" required>
                    <option value="makanan utama">makanan utama</option>
                    <option value="cemilan">cemilan</option>
                    <option value="minuman">minuman</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Harga</label>
                <input type="number" class="w-full border border-slate-300 rounded-xl px-4 py-3" min="0" required>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Deskripsi</label>
                <textarea class="w-full border border-slate-300 rounded-xl px-4 py-3" rows="4"></textarea>
            </div>
            <div class="flex gap-3">
                <a href="/backoffice/daftar_menu" class="flex-1 text-center border-2 border-slate-300 rounded-xl py-3 font-bold">Batal</a>
                <button type="submit" class="flex-1 bg-orange-600 hover:bg-orange-700 text-white rounded-xl py-3 font-bold transition">Simpan</button>
            </div>
        </form>
    </main>
</body>
</html>
