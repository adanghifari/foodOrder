@php
    $removeRequested = old('remove_image', '0') === '1';
    $hasCurrentImage = !$removeRequested && !empty($menu->image_url);
    $previewImage = $menu->image_url ?: 'https://placehold.co/900x500/f3f4f6/64748b?text=No+Image';
@endphp

<x-backoffice.menu.modal
    title="Detail lengkap data menu"
    :subtitle="$menu->name"
    closeHref="/backoffice/daftar_menu"
    :imageUrl="null"
    :imageAlt="''"
>
    <form id="edit-menu-form" action="/backoffice/daftar_menu/{{ (string) $menu->_id }}" method="POST" enctype="multipart/form-data" class="contents">
        @csrf
        @method('PUT')
        <input type="hidden" id="remove_image" name="remove_image" value="{{ old('remove_image', '0') }}">

        <x-backoffice.menu.field label="Gambar Menu" :colSpan="true">
            <div id="image-container" class="relative h-44 rounded-lg overflow-hidden border border-slate-200 bg-slate-100">
                <img id="image-preview" src="{{ $previewImage }}" alt="Preview gambar menu" class="h-full w-full object-cover {{ $hasCurrentImage ? '' : 'hidden' }}">

                <button
                    type="button"
                    id="remove-image-corner"
                    class="absolute top-2 right-2 inline-flex items-center justify-center h-8 w-8 rounded-full bg-white/95 border border-red-200 text-red-700 font-bold shadow-sm hover:bg-red-50 transition {{ $hasCurrentImage ? '' : 'hidden' }}"
                    aria-label="Hapus gambar"
                >
                    ✕
                </button>

                <label
                    for="image"
                    id="add-image-center"
                    class="absolute inset-0 {{ $hasCurrentImage ? 'hidden' : 'flex' }} items-center justify-center cursor-pointer"
                >
                    <span class="inline-flex items-center rounded-xl border border-slate-300 bg-white/95 hover:bg-white text-slate-700 text-sm font-bold px-4 py-2 transition">Tambah File</span>
                </label>
            </div>

        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Nama">
            <input id="name" name="name" type="text" value="{{ old('name', $menu->name) }}" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" required>
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Kategori">
            <select id="category" name="category" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" required>
                @foreach ($allowedCategories as $category)
                    <option value="{{ $category }}" {{ old('category', $menu->category) === $category ? 'selected' : '' }}>{{ $category }}</option>
                @endforeach
            </select>
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Harga">
            <input id="price" name="price" type="number" min="0" step="0.01" value="{{ old('price', $menu->price) }}" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" required>
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Stok">
            <input id="stock" name="stock" type="number" min="0" value="{{ old('stock', (int) ($menu->stock ?? 0)) }}" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" required>
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Deskripsi" :colSpan="true">
            <textarea id="description" name="description" rows="2" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm">{{ old('description', $menu->description) }}</textarea>
        </x-backoffice.menu.field>

        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" class="hidden">
    </form>

    <x-slot:footer>
        <div class="w-full flex items-center justify-end gap-3">
            <a href="/backoffice/daftar_menu" class="inline-flex items-center rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-bold px-4 py-2.5 transition">Batal</a>
            <button type="submit" form="edit-menu-form" class="inline-flex items-center rounded-xl bg-[var(--alloy-orange)] hover:bg-[var(--philippine-bronze)] text-white text-sm font-bold px-4 py-2.5 transition">Simpan Perubahan</button>
        </div>
    </x-slot:footer>
</x-backoffice.menu.modal>

<script>
    (function () {
        const removeInput = document.getElementById('remove_image');
        const removeCornerButton = document.getElementById('remove-image-corner');
        const addCenter = document.getElementById('add-image-center');
        const imagePreview = document.getElementById('image-preview');
        const fileInput = document.getElementById('image');

        if (removeCornerButton && removeInput && imagePreview && addCenter) {
            removeCornerButton.addEventListener('click', function () {
                removeInput.value = '1';
                if (fileInput) {
                    fileInput.value = '';
                }

                imagePreview.classList.add('hidden');
                removeCornerButton.classList.add('hidden');
                addCenter.classList.remove('hidden');
                addCenter.classList.add('flex');
            });
        }

        if (fileInput && removeInput && imagePreview && addCenter && removeCornerButton) {
            fileInput.addEventListener('change', function () {
                if (fileInput.files && fileInput.files.length > 0) {
                    removeInput.value = '0';

                    const reader = new FileReader();
                    reader.onload = function (event) {
                        imagePreview.src = String(event.target?.result || '');
                        imagePreview.classList.remove('hidden');
                        removeCornerButton.classList.remove('hidden');
                        addCenter.classList.add('hidden');
                        addCenter.classList.remove('flex');
                    };
                    reader.readAsDataURL(fileInput.files[0]);
                }
            });
        }
    })();
</script>
