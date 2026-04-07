@php
    $hasUploadError = $errors->has('image');
@endphp

<x-backoffice.menu.modal
    title="Detail lengkap data menu"
    subtitle="Tambah menu baru"
    closeHref="/backoffice/daftar_menu"
    :imageUrl="null"
    maxWidth="max-w-3xl"
    scrollableBody="true"
    bodyMaxHeightClass="max-h-[58vh]"
>
    <form id="create-menu-form" action="/backoffice/daftar_menu" method="POST" enctype="multipart/form-data" class="contents">
        @csrf

        <x-backoffice.menu.field label="Gambar Menu" :colSpan="true">
            <div class="relative h-44 rounded-lg overflow-hidden border border-slate-200 bg-slate-100">
                <img id="create-image-preview" src="" alt="Preview gambar menu" class="h-full w-full object-cover hidden">

                <button
                    type="button"
                    id="create-remove-image-corner"
                    class="absolute top-2 right-2 inline-flex items-center justify-center h-8 w-8 rounded-full bg-white/95 border border-red-200 text-red-700 font-bold shadow-sm hover:bg-red-50 transition hidden"
                    aria-label="Hapus gambar"
                >
                    ✕
                </button>

                <label
                    for="create-image"
                    id="create-add-image-center"
                    class="absolute inset-0 flex items-center justify-center cursor-pointer"
                >
                    <span class="inline-flex items-center rounded-xl border border-slate-300 bg-white/95 hover:bg-white text-slate-700 text-sm font-bold px-4 py-2 transition">Tambah File</span>
                </label>
            </div>
            <input id="create-image" name="image" type="file" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" class="hidden">
            @if ($hasUploadError)
                <p class="mt-2 text-xs font-semibold text-red-600">{{ $errors->first('image') }}</p>
            @endif
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Nama">
            <input id="name" name="name" type="text" value="{{ old('name') }}" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" required>
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Kategori">
            <select id="category" name="category" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" required>
                @foreach ($allowedCategories as $category)
                    <option value="{{ $category }}" {{ old('category') === $category ? 'selected' : '' }}>{{ $category }}</option>
                @endforeach
            </select>
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Harga">
            <input id="price" name="price" type="number" min="0" step="0.01" value="{{ old('price') }}" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" required>
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Stok">
            <input id="stock" name="stock" type="number" min="0" value="{{ old('stock', 0) }}" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" required>
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Deskripsi" :colSpan="true">
            <textarea id="description" name="description" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm">{{ old('description') }}</textarea>
        </x-backoffice.menu.field>
    </form>

    <x-slot:footer>
        <div class="w-full flex items-center justify-end gap-3">
            <a href="/backoffice/daftar_menu" class="inline-flex items-center rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-bold px-4 py-2.5 transition">Batal</a>
            <button type="submit" form="create-menu-form" class="inline-flex items-center rounded-xl bg-[var(--alloy-orange)] hover:bg-[var(--philippine-bronze)] text-white text-sm font-bold px-4 py-2.5 transition">Simpan</button>
        </div>
    </x-slot:footer>
</x-backoffice.menu.modal>

<script>
    (function () {
        const fileInput = document.getElementById('create-image');
        const preview = document.getElementById('create-image-preview');
        const addCenter = document.getElementById('create-add-image-center');
        const removeButton = document.getElementById('create-remove-image-corner');

        if (!fileInput || !preview || !addCenter || !removeButton) {
            return;
        }

        fileInput.addEventListener('change', function () {
            if (!fileInput.files || fileInput.files.length === 0) {
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                preview.src = String(event.target?.result || '');
                preview.classList.remove('hidden');
                addCenter.classList.add('hidden');
                removeButton.classList.remove('hidden');
            };
            reader.readAsDataURL(fileInput.files[0]);
        });

        removeButton.addEventListener('click', function () {
            fileInput.value = '';
            preview.src = '';
            preview.classList.add('hidden');
            addCenter.classList.remove('hidden');
            removeButton.classList.add('hidden');
        });
    })();
</script>
