<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pesanan Backoffice</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 p-6 text-slate-900">
    <main class="max-w-6xl mx-auto space-y-5">
        <header class="bg-white border border-slate-200 rounded-2xl p-5">
            <h1 class="text-2xl font-extrabold">Daftar Pesanan</h1>
            <p class="text-slate-500">Monitor status order customer dari backoffice.</p>
        </header>

        <section class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-200 text-sm text-slate-600">
                    <tr>
                        <th class="px-4 py-3">Order ID</th>
                        <th class="px-4 py-3">Meja</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Total</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3">ORD-00124</td>
                        <td class="px-4 py-3">2</td>
                        <td class="px-4 py-3">Andi</td>
                        <td class="px-4 py-3"><span class="bg-amber-100 text-amber-700 px-2 py-1 rounded-lg text-xs font-bold">IN_QUEUE</span></td>
                        <td class="px-4 py-3">Rp 67.000</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">ORD-00125</td>
                        <td class="px-4 py-3">5</td>
                        <td class="px-4 py-3">Sinta</td>
                        <td class="px-4 py-3"><span class="bg-green-100 text-green-700 px-2 py-1 rounded-lg text-xs font-bold">DELIVERED</span></td>
                        <td class="px-4 py-3">Rp 102.000</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
