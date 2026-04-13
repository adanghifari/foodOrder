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

<section class="{{ $zIndex }} fixed top-0 left-0 w-screen h-screen">
    <a href="{{ $closeHref }}" class="fixed top-0 left-0 w-screen h-screen {{ $overlayClass }}" aria-label="Tutup"></a>

    <div class="relative z-[121] w-screen h-screen flex items-center justify-center p-4 md:p-5">
        <article class="w-full {{ $maxWidth }} rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3.5 border-b border-slate-200">
                <div>
                    <h2 class="text-lg md:text-xl font-extrabold text-[var(--rich-black)]">{{ $title }}</h2>
                    @if ($subtitle)
                        <p class="text-sm text-slate-500">{{ $subtitle }}</p>
                    @endif
                </div>
                <a href="{{ $closeHref }}" class="inline-flex items-center justify-center h-9 w-9 rounded-lg border border-slate-300 hover:bg-slate-100 text-slate-600 font-bold transition" aria-label="Tutup">✕</a>
            </div>

            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ $imageAlt }}" class="w-full h-40 md:h-52 object-cover">
            @endif

            <div class="p-4 md:p-5 {{ $scrollableBody ? $bodyMaxHeightClass . ' overflow-y-auto' : '' }}">
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
