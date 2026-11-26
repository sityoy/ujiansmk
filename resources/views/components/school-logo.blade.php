@props(['school' => null, 'customOnly' => false])

@if ((! $customOnly || $school?->logo_path) && ($url = \App\Models\SchoolProfile::logoUrl($school)))
    <img src="{{ $url }}" alt="Logo sekolah" {{ $attributes->except('style') }} style="object-fit:contain;{{ $attributes->get('style') }}">
@endif
