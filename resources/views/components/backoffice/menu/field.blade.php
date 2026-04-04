@props([
    'label',
    'colSpan' => false,
])

<div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 {{ $colSpan ? 'md:col-span-2' : '' }}">
    <p class="text-xs font-semibold text-slate-500 mb-1">{{ $label }}</p>
    {{ $slot }}
</div>
