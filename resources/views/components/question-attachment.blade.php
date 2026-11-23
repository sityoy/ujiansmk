@props(['path', 'alt', 'linkLabel' => null])

@if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true))
    <x-exam-image :path="$path" :alt="$alt" {{ $attributes }} />
@else
    <a href="{{ Storage::url($path) }}" target="_blank" rel="noopener" {{ $attributes->class('inline-block text-xs font-semibold text-cyan-300') }}>{{ $linkLabel ?? 'Buka '.$alt }}</a>
@endif
