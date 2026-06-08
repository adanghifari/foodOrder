@php
    $hasUploadError = $errors->has('image');
    $selectedCategory = old('category', $allowedCategories[0] ?? 'makanan utama');
    $selectedTags = old('tags', []);
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
            <select id="create-category" name="category" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" required>
                @foreach ($allowedCategories as $category)
                    <option value="{{ $category }}" {{ $selectedCategory === $category ? 'selected' : '' }}>{{ ucwords($category) }}</option>
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

        <x-backoffice.menu.field label="Level Pedas (0-5)" :colSpan="true" id="create-spice-wrap">
            <input id="create-spice-level" name="spice_level" type="number" min="0" max="5" value="{{ old('spice_level') }}" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" placeholder="Kosongkan jika tidak relevan">
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Level Manis (0-5)" :colSpan="true" id="create-sweet-wrap">
            <input id="create-sweet-level" name="sweet_level" type="number" min="0" max="5" value="{{ old('sweet_level') }}" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" placeholder="Kosongkan jika tidak relevan">
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Level Fresh (0-5)" :colSpan="true" id="create-fresh-wrap">
            <input id="create-fresh-level" name="fresh_level" type="number" min="0" max="5" value="{{ old('fresh_level') }}" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" placeholder="Khusus kategori minuman">
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Kalori" :colSpan="true" id="create-calorie-wrap">
            <select id="create-calorie-level" name="calorie_level" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm">
                <option value="">Pilih level kalori</option>
                @foreach ($calorieLevels as $level)
                    <option value="{{ $level }}" {{ old('calorie_level') === $level ? 'selected' : '' }}>{{ strtoupper($level) }}</option>
                @endforeach
            </select>
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Tag Menu" :colSpan="true" id="create-tags-wrap">
            <div id="create-tags-list" class="grid grid-cols-1 md:grid-cols-3 gap-2"></div>
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Catatan Rekomendasi Admin" :colSpan="true">
            <textarea id="recommendation_note" name="recommendation_note" rows="2" maxlength="500" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm" placeholder="Opsional. Contoh: cocok untuk jam sarapan, best seller hari kerja, dll.">{{ old('recommendation_note') }}</textarea>
        </x-backoffice.menu.field>

        <x-backoffice.menu.field label="Catatan Rasa" :colSpan="true">
            <p class="text-xs text-slate-500">Metadata rasa menyesuaikan kategori menu.</p>
        </x-backoffice.menu.field>
    </form>

    <script>
        (function () {
            const fileInput = document.getElementById('create-image');
            const preview = document.getElementById('create-image-preview');
            const addCenter = document.getElementById('create-add-image-center');
            const removeButton = document.getElementById('create-remove-image-corner');
            let currentPreviewUrl = '';

            if (!fileInput || !preview || !addCenter || !removeButton) {
                return;
            }

            function clearObjectUrl() {
                if (currentPreviewUrl && currentPreviewUrl.indexOf('blob:') === 0) {
                    URL.revokeObjectURL(currentPreviewUrl);
                }
                currentPreviewUrl = '';
            }

            function showPreviewFromFile(file) {
                if (!file) {
                    return;
                }

                clearObjectUrl();

                if (window.URL && typeof window.URL.createObjectURL === 'function') {
                    currentPreviewUrl = window.URL.createObjectURL(file);
                    preview.src = currentPreviewUrl;
                    preview.classList.remove('hidden');
                    addCenter.classList.add('hidden');
                    removeButton.classList.remove('hidden');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (event) {
                    const result = event && event.target ? event.target.result : '';
                    preview.src = String(result || '');
                    preview.classList.remove('hidden');
                    addCenter.classList.add('hidden');
                    removeButton.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }

            fileInput.addEventListener('change', function () {
                const selectedFile = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                showPreviewFromFile(selectedFile);
            });

            removeButton.addEventListener('click', function () {
                clearObjectUrl();
                fileInput.value = '';
                preview.src = '';
                preview.classList.add('hidden');
                addCenter.classList.remove('hidden');
                removeButton.classList.add('hidden');
            });

            window.addEventListener('beforeunload', clearObjectUrl);
        })();

        (function () {
            const categoryTagMap = @json($categoryTagMap);
            const categoryMetadataMap = @json($categoryMetadataMap);
            const selectedTags = @json(array_values($selectedTags));
            const categorySelect = document.getElementById('create-category');
            const tagsList = document.getElementById('create-tags-list');
            const spiceWrap = document.getElementById('create-spice-wrap');
            const sweetWrap = document.getElementById('create-sweet-wrap');
            const freshWrap = document.getElementById('create-fresh-wrap');
            const calorieWrap = document.getElementById('create-calorie-wrap');
            const spiceInput = document.getElementById('create-spice-level');
            const sweetInput = document.getElementById('create-sweet-level');
            const freshInput = document.getElementById('create-fresh-level');

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
            <button type="submit" form="create-menu-form" class="inline-flex items-center rounded-xl bg-[var(--alloy-orange)] hover:bg-[var(--philippine-bronze)] text-white text-sm font-bold px-4 py-2.5 transition">Simpan</button>
        </div>
    </x-slot:footer>
</x-backoffice.menu.modal>
