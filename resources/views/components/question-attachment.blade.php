@props(['path', 'alt', 'linkLabel' => null, 'caption' => null])

<figure {{ $attributes }}>
    @if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true))
        <x-exam-image :path="$path" :alt="$alt" />
    @else
        <a href="{{ Storage::url($path) }}" target="_blank" rel="noopener" class="inline-block text-xs font-semibold text-cyan-300">{{ $linkLabel ?? 'Buka '.$alt }}</a>
    @endif
    @if (filled($caption))
        <figcaption class="mt-1 whitespace-pre-wrap break-words text-sm leading-relaxed text-slate-300">{{ $caption }}</figcaption>
    @endif
</figure>
