@props(['name', 'label', 'alt', 'path' => null, 'caption' => null, 'disabled' => false, 'showSaved' => true])

<div data-question-file class="space-y-3">
    <label class="block text-xs font-semibold text-slate-300">
        {{ $label }}
        <input type="file" name="{{ $name }}" accept="image/jpeg,image/png,image/webp,application/pdf" @disabled($disabled)
            class="mt-2 block w-full cursor-pointer rounded-xl border border-cyan-400/40 bg-slate-950/60 p-2 text-xs text-slate-300 focus-visible:outline-2 focus-visible:outline-cyan-400 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-cyan-400 file:px-3 file:py-2 file:font-semibold file:text-slate-950">
    </label>
    @if ($path)
        <div data-question-file-saved @if (! $showSaved) hidden @endif class="space-y-2">
            <p class="text-xs text-slate-400">Lampiran saat ini. Pilih file baru di atas untuk menggantinya.</p>
            <x-question-attachment :path="$path" :alt="$alt" />
            <label class="flex items-center gap-2 text-xs font-medium text-rose-300">
                <input type="checkbox" name="remove_{{ $name }}" value="1" @checked(old('remove_'.$name))
                    @disabled($disabled || ! $showSaved)>
                Hapus lampiran saat disimpan
            </label>
        </div>
    @endif
    <div data-question-file-preview hidden>
        <p class="mb-1 text-xs text-slate-400">Pratinjau gambar baru</p>
        <x-exam-image :alt="$alt" />
    </div>
    <button type="button" data-question-file-clear hidden class="rounded-lg border border-white/20 px-3 py-1.5 text-xs font-medium text-slate-300">
        Batal pilih file baru
    </button>
    <label class="block text-xs font-medium text-slate-300">Keterangan gambar (opsional)
        <textarea name="{{ $name }}_caption" maxlength="500" rows="2" @disabled($disabled)
            class="mt-1 block w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm"
            placeholder="Tulis keterangan yang akan tampil di bawah gambar">{{ old($name.'_caption', $caption) }}</textarea>
    </label>
</div>
