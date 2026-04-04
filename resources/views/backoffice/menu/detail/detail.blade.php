@php
    $stock = (int) ($menu->stock ?? 0);
    $stockClass = $stock <= 0
        ? 'bg-red-100 text-red-700'
        : ($stock <= 10 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700');
    $imageUrl = $menu->image_url ?: 'https://placehold.co/1200x800/f3f4f6/64748b?text=No+Image';
@endphp

<x-backoffice.menu.modal
    title="Detail lengkap data menu"
    :subtitle="$menu->name"
    closeHref="/backoffice/daftar_menu"
    :imageUrl="$imageUrl"
    :imageAlt="$menu->name"
>
    <x-backoffice.menu.field label="Nama">
        <p class="text-sm font-bold text-[var(--rich-black)]">{{ $menu->name }}</p>
    </x-backoffice.menu.field>

    <x-backoffice.menu.field label="Kategori">
        <p class="text-sm font-bold text-[var(--rich-black)]">{{ $menu->category ?? '-' }}</p>
    </x-backoffice.menu.field>

    <x-backoffice.menu.field label="Harga">
        <p class="text-sm font-bold text-[var(--rich-black)]">Rp {{ number_format((float) ($menu->price ?? 0), 0, ',', '.') }}</p>
    </x-backoffice.menu.field>

    <x-backoffice.menu.field label="Stok">
        <span class="inline-flex text-xs font-bold px-2.5 py-1 rounded-full {{ $stockClass }}">{{ $stock }}</span>
    </x-backoffice.menu.field>

    <x-backoffice.menu.field label="Deskripsi" :colSpan="true">
        <p class="text-sm font-medium text-slate-700">{{ $menu->description ?: '-' }}</p>
    </x-backoffice.menu.field>

    <x-slot:footer>
        <a href="/backoffice/daftar_menu/{{ urlencode((string) $menu->_id) }}/edit" class="inline-flex items-center rounded-xl bg-[var(--alloy-orange)] hover:bg-[var(--philippine-bronze)] text-white text-sm font-bold px-4 py-2.5 transition">Edit Menu</a>
    </x-slot:footer>
</x-backoffice.menu.modal>
