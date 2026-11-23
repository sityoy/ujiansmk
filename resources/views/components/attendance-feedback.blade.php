@props(['state' => 'pending', 'title' => null, 'message'])

@php
    $labels = ['pending' => 'Menunggu absensi', 'checking' => 'Sedang memeriksa', 'success' => 'Absensi berhasil', 'error' => 'Absensi gagal'];
@endphp
<div data-attendance-feedback data-state="{{ $state }}" role="status" aria-atomic="true"
    {{ $attributes->class('attendance-feedback') }}>
    @foreach ($labels as $value => $label)
        <strong data-attendance-label="{{ $value }}">{{ $state === $value ? ($title ?? $label) : $label }}</strong>
    @endforeach
    <p data-attendance-message>{{ $message }}</p>
</div>
