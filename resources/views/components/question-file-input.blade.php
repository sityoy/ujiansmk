@props(['name', 'label', 'alt', 'path' => null, 'showLabel' => true])

<div data-question-file>
    <label class="block text-xs text-slate-400">
        <span @class(['sr-only' => ! $showLabel])>{{ $label }}</span>
        <input type="file" name="{{ $name }}" accept="image/jpeg,image/png,image/webp,application/pdf" {{ $attributes }}>
    </label>
    @if ($path)
        <div class="mt-2">
            <p class="mb-1 text-xs text-slate-400">Lampiran saat ini</p>
            <x-question-attachment :path="$path" :alt="$alt" />
        </div>
    @endif
    <div data-question-file-preview hidden class="mt-2">
        <p class="mb-1 text-xs text-slate-400">Pratinjau gambar baru</p>
        <x-exam-image :alt="$alt" />
    </div>
</div>
