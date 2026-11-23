@props(['path' => null, 'alt'])

<button type="button" data-exam-image aria-haspopup="dialog" aria-label="Lihat {{ $alt }}"
    {{ $attributes->class('block max-w-full cursor-zoom-in rounded-xl border border-slate-300 bg-white p-2 text-slate-800 focus-visible:outline-2 focus-visible:outline-cyan-400') }}>
    <img @if ($path !== null) src="{{ Storage::url($path) }}" @endif alt="{{ $alt }}" loading="lazy" decoding="async"
        class="max-h-48 max-w-full object-contain sm:max-h-56">
    <span class="mt-1 block text-xs font-semibold">Ketuk untuk perbesar</span>
</button>
