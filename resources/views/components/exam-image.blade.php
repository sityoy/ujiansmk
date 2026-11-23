@props(['path' => null, 'alt'])

<button type="button" data-exam-image aria-haspopup="dialog" aria-label="Perbesar atau perkecil {{ $alt }}"
    {{ $attributes->class('block max-w-full cursor-zoom-in rounded-xl border border-slate-300 bg-white p-2 text-slate-800 focus-visible:outline-2 focus-visible:outline-cyan-400') }}>
    <img @if ($path !== null) src="{{ Storage::url($path) }}" @endif alt="{{ $alt }}" loading="lazy" decoding="async"
        class="max-h-48 max-w-full object-contain sm:max-h-56">
    <span class="exam-image-action">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4M7.5 10.5h6m-3-3v6"/>
        </svg>
        Perbesar / Perkecil gambar
    </span>
</button>
