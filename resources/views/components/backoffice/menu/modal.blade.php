@props([
    'title',
    'subtitle' => null,
    'closeHref' => '/backoffice/daftar_menu',
    'imageUrl' => null,
    'imageAlt' => 'Menu image',
    'maxWidth' => 'max-w-2xl',
    'overlayClass' => 'bg-white/15 backdrop-blur-sm',
    'zIndex' => 'z-[120]',
    'scrollableBody' => false,
    'bodyMaxHeightClass' => 'max-h-[44vh]',
])

<section data-modal-root class="bo-modal-root {{ $zIndex }} fixed top-0 left-0 w-screen h-screen">
    <button type="button" data-modal-overlay class="bo-modal-backdrop fixed top-0 left-0 w-screen h-screen {{ $overlayClass }}" aria-label="Tutup"></button>

    <div class="relative z-[121] w-screen h-screen flex items-center justify-center p-3 md:p-5">
        <article class="bo-modal-panel w-full {{ $maxWidth }} max-h-[90vh] md:max-h-[88vh] rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-4 py-3.5 border-b border-slate-200">
                <div>
                    <h2 class="text-lg md:text-xl font-extrabold text-[var(--rich-black)]">{{ $title }}</h2>
                    @if ($subtitle)
                        <p class="text-sm text-slate-500">{{ $subtitle }}</p>
                    @endif
                </div>
                <button type="button" data-modal-close class="inline-flex items-center justify-center h-9 w-9 rounded-lg border border-slate-300 hover:bg-slate-100 text-slate-600 font-bold transition" aria-label="Tutup">✕</button>
            </div>

            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ $imageAlt }}" class="w-full h-40 md:h-52 object-cover">
            @endif

            <div class="p-4 md:p-5 overflow-y-auto min-h-0 {{ $scrollableBody ? $bodyMaxHeightClass : 'flex-1' }}">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    {{ $slot }}
                </div>
            </div>

            @if (isset($footer))
                <div class="px-4 pb-4 md:px-5 md:pb-5 flex justify-end">
                    {{ $footer }}
                </div>
            @endif
        </article>
    </div>
</section>
