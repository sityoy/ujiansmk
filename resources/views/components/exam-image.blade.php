@props(['path', 'alt'])

<button type="button" data-exam-image aria-haspopup="dialog" aria-label="Lihat {{ $alt }}"
    class="block max-w-full cursor-zoom-in rounded-xl border border-white/10 bg-white p-2 focus-visible:outline-2 focus-visible:outline-cyan-400">
    <img src="{{ Storage::url($path) }}" alt="{{ $alt }}" loading="lazy" decoding="async"
        class="max-h-48 max-w-full object-contain sm:max-h-56">
</button>
