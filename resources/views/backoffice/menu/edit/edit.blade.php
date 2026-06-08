@php
    $removeRequested = old('remove_image', '0') === '1';
    $hasCurrentImage = !$removeRequested && !empty($menu->image_url);
    $previewImage = $menu->image_url ?: 'https://placehold.co/900x500/f3f4f6/64748b?text=No+Image';
    $selectedTags = old('tags', is_array($menu->tags ?? null) ? $menu->tags : []);
    $selectedCategory = old('category', (string) ($menu->category ?? 'makanan utama'));
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
                <img id="image-preview" src="{{ $previewImage }}" alt="Preview gambar menu" class="h-full w-full object-cover pointer-events-none {{ $hasCurrentImage ? '' : 'hidden' }}">

                <button
                    type="button"
                    id="remove-image-corner"
                    class="absolute top-2 right-2 z-30 pointer-events-auto inline-flex items-center justify-center h-8 w-8 rounded-full bg-white/95 border border-red-200 text-red-700 font-bold shadow-sm hover:bg-red-50 transition {{ $hasCurrentImage ? '' : 'hidden' }}"
                    aria-label="Hapus gambar"
                >
                    ✕
                </button>

                <label
                    for="image"
                    id="add-image-center"
                    class="absolute inset-0 z-10 {{ $hasCurrentImage ? 'hidden' : 'flex' }} items-center justify-center cursor-pointer"
                >
                    <span class="inline-flex items-center rounded-xl border border-slate-300 bg-white/95 hover:bg-white text-slate-700 text-sm font-bold px-4 py-2 transition">Tambah File</span>
                </label>
            </div>

        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Nama">
            <input id="name" name="name" type="text" value="{{ old('name', $menu->name) }}" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" required>
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Kategori">
            <select id="edit-category" name="category" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" required>
                @foreach ($allowedCategories as $category)
                    <option value="{{ $category }}" {{ $selectedCategory === $category ? 'selected' : '' }}>{{ ucwords($category) }}</option>
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

        <x-backoffice.menu.field label="Level Pedas (0-5)" :colSpan="true" id="edit-spice-wrap">
            <input id="edit-spice-level" name="spice_level" type="number" min="0" max="5" value="{{ old('spice_level', $menu->spice_level) }}" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" placeholder="Kosongkan jika tidak relevan">
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Level Manis (0-5)" :colSpan="true" id="edit-sweet-wrap">
            <input id="edit-sweet-level" name="sweet_level" type="number" min="0" max="5" value="{{ old('sweet_level', $menu->sweet_level) }}" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" placeholder="Kosongkan jika tidak relevan">
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Level Fresh (0-5)" :colSpan="true" id="edit-fresh-wrap">
            <input id="edit-fresh-level" name="fresh_level" type="number" min="0" max="5" value="{{ old('fresh_level', $menu->fresh_level) }}" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" placeholder="Khusus kategori minuman">
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Kalori" :colSpan="true" id="edit-calorie-wrap">
            <select id="edit-calorie-level" name="calorie_level" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm">
                <option value="">Pilih level kalori</option>
                @foreach ($calorieLevels as $level)
                    <option value="{{ $level }}" {{ old('calorie_level', $menu->calorie_level) === $level ? 'selected' : '' }}>{{ strtoupper($level) }}</option>
                @endforeach
            </select>
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Tag Menu" :colSpan="true" id="edit-tags-wrap">
            <div id="edit-tags-list" class="grid grid-cols-1 md:grid-cols-3 gap-2"></div>
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Catatan Rekomendasi Admin" :colSpan="true">
            <textarea id="recommendation_note" name="recommendation_note" rows="2" maxlength="500" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" placeholder="Opsional. Contoh: cocok untuk jam sarapan, best seller hari kerja, dll.">{{ old('recommendation_note', $menu->recommendation_note) }}</textarea>
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Catatan Rasa" :colSpan="true">
            <p class="text-xs text-slate-500">Metadata rasa menyesuaikan kategori menu.</p>
        </x-backoffice.menu.field>

        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" class="hidden">
    </form>

    <script>
    (function () {
        const removeInput = document.getElementById('remove_image');
        const removeCornerButton = document.getElementById('remove-image-corner');
        const addCenter = document.getElementById('add-image-center');
        const imagePreview = document.getElementById('image-preview');
        const fileInput = document.getElementById('image');
        const imageContainer = document.getElementById('image-container');
        let currentPreviewUrl = '';

        function clearObjectUrl() {
            if (currentPreviewUrl && currentPreviewUrl.indexOf('blob:') === 0) {
                URL.revokeObjectURL(currentPreviewUrl);
            }
            currentPreviewUrl = '';
        }

        function setPreviewFromFile(file) {
            if (!file) {
                return;
            }

            clearObjectUrl();

            if (window.URL && typeof window.URL.createObjectURL === 'function') {
                currentPreviewUrl = window.URL.createObjectURL(file);
                imagePreview.src = currentPreviewUrl;
                imagePreview.classList.remove('hidden');
                removeCornerButton.classList.remove('hidden');
                addCenter.classList.add('hidden');
                addCenter.classList.remove('flex');
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                const result = event && event.target ? event.target.result : '';
                imagePreview.src = String(result || '');
                imagePreview.classList.remove('hidden');
                removeCornerButton.classList.remove('hidden');
                addCenter.classList.add('hidden');
                addCenter.classList.remove('flex');
            };
            reader.readAsDataURL(file);
        }

        if (removeCornerButton && removeInput && imagePreview && addCenter) {
            removeCornerButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

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
                    setPreviewFromFile(fileInput.files[0]);
                }
            });
        }

        if (imageContainer && fileInput && imagePreview && removeCornerButton) {
            imageContainer.addEventListener('click', function (event) {
                if (event.target === removeCornerButton || event.target.closest('#remove-image-corner')) {
                    return;
                }

                if (event.target.closest('#add-image-center')) {
                    return;
                }

                const hasVisiblePreview = !imagePreview.classList.contains('hidden');
                if (hasVisiblePreview) {
                    fileInput.click();
                }
            });
        }

        window.addEventListener('beforeunload', clearObjectUrl);
    })();

    (function () {
        const categoryTagMap = @json($categoryTagMap);
        const categoryMetadataMap = @json($categoryMetadataMap);
        const selectedTags = @json(array_values($selectedTags));
        const categorySelect = document.getElementById('edit-category');
        const tagsList = document.getElementById('edit-tags-list');
        const spiceWrap = document.getElementById('edit-spice-wrap');
        const sweetWrap = document.getElementById('edit-sweet-wrap');
        const freshWrap = document.getElementById('edit-fresh-wrap');
        const calorieWrap = document.getElementById('edit-calorie-wrap');
        const spiceInput = document.getElementById('edit-spice-level');
        const sweetInput = document.getElementById('edit-sweet-level');
        const freshInput = document.getElementById('edit-fresh-level');

        if (!categorySelect || !tagsList || !spiceWrap || !sweetWrap || !freshWrap || !calorieWrap || !spiceInput || !sweetInput || !freshInput) {
            return;
        }

        const selectedTagSet = new Set(selectedTags.map((tag) => String(tag)));

        function normalizeCategory(value) {
            return String(value || '').toLowerCase().trim();
        }

        function visibleFields(category) {
            return Array.isArray(categoryMetadataMap[category]) ? categoryMetadataMap[category] : [];
        }

        function setVisible(fieldWrap, visible) {
            fieldWrap.classList.toggle('hidden', !visible);
        }

        function syncMetadataVisibility() {
            const category = normalizeCategory(categorySelect.value);
            const fields = visibleFields(category);
            const hasField = (name) => fields.includes(name);

            setVisible(spiceWrap, hasField('spice_level'));
            setVisible(sweetWrap, hasField('sweet_level'));
            setVisible(freshWrap, hasField('fresh_level'));
            setVisible(calorieWrap, hasField('calorie_level'));

            if (!hasField('spice_level')) spiceInput.value = '';
            if (!hasField('sweet_level')) sweetInput.value = '';
            if (!hasField('fresh_level')) freshInput.value = '';
        }

        function renderTags() {
            const category = normalizeCategory(categorySelect.value);
            const tags = Array.isArray(categoryTagMap[category]) ? categoryTagMap[category] : [];
            tagsList.innerHTML = '';

            selectedTagSet.forEach((tag) => {
                if (!tags.includes(tag)) {
                    selectedTagSet.delete(tag);
                }
            });

            tags.forEach((tag) => {
                const label = document.createElement('label');
                label.className = 'inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm';
                const checked = selectedTagSet.has(tag) ? 'checked' : '';
                label.innerHTML = `<input type="checkbox" name="tags[]" value="${tag}" ${checked}><span>${tag}</span>`;
                const input = label.querySelector('input');
                if (input) {
                    input.addEventListener('change', function () {
                        if (input.checked) selectedTagSet.add(tag);
                        else selectedTagSet.delete(tag);
                    });
                }
                tagsList.appendChild(label);
            });
        }

        categorySelect.addEventListener('change', function () {
            syncMetadataVisibility();
            renderTags();
        });

        syncMetadataVisibility();
        renderTags();
    })();
</script>

    <x-slot:footer>
        <div class="w-full flex items-center justify-end gap-3">
            <a href="/backoffice/daftar_menu" class="inline-flex items-center rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-bold px-4 py-2.5 transition">Batal</a>
            <button type="submit" form="edit-menu-form" class="inline-flex items-center rounded-xl bg-[var(--alloy-orange)] hover:bg-[var(--philippine-bronze)] text-white text-sm font-bold px-4 py-2.5 transition">Simpan Perubahan</button>
        </div>
    </x-slot:footer>
</x-backoffice.menu.modal>
