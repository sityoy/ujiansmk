@props(['path', 'label'])

@php
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    $url = url($disk->url($path));
    $isImage = in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
@endphp
@if (! $disk->exists($path))
    <p class="attachment-reference">{{ $label }} tidak tersedia.</p>
@elseif ($isImage)
    <img class="question-image" src="{{ $url }}" alt="{{ $label }}">
@else
    <p class="attachment-reference">{{ $label }} <a href="{{ $url }}">{{ $url }}</a></p>
@endif
