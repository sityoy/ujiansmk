@props(['path', 'label'])

<div class="px-4 pb-3 pl-10">
    @if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true))
        <x-exam-image :path="$path" :alt="'Lampiran pilihan '.$label" />
    @else
        <a href="{{ Storage::url($path) }}" target="_blank" rel="noopener" class="text-xs font-semibold text-cyan-300">Buka lampiran pilihan {{ $label }}</a>
    @endif
</div>
