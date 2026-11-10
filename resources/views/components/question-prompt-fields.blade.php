@props(['question' => null, 'multipleChoice' => true])

<div data-question-prompt-fields class="space-y-3">
    <label data-question-statement class="block text-xs text-slate-400" @if (! $multipleChoice) hidden @endif>
        Pernyataan (opsional)
        <textarea name="statement" rows="4" maxlength="5000" @disabled(! $multipleChoice)
            placeholder="Tuliskan bacaan, konteks, atau butir (1), (2), (3)..."
            class="mt-2 block w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">{{ $question ? $question->statement : old('statement') }}</textarea>
        <span class="mt-2 block">Gunakan baris terpisah untuk setiap butir bernomor.</span>
    </label>
    <div data-question-attachment>
        <label class="block text-xs text-slate-400">
            Gambar atau PDF (opsional, maks. 5 MB)
            <input type="file" name="question_image" accept="image/jpeg,image/png,image/webp,application/pdf"
                class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-cyan-400 file:px-3 file:py-2 file:font-semibold file:text-slate-950">
        </label>
        <p class="mt-2 text-xs text-slate-400">Soal Bahasa Arab disarankan memakai gambar agar huruf dan harakat tetap rapi.</p>
        @if ($question?->question_image_path)
            <a href="{{ Storage::url($question->question_image_path) }}" target="_blank" rel="noopener" class="mt-2 inline-block text-xs text-cyan-300">Lihat lampiran saat ini</a>
        @endif
    </div>
    <label data-question-text class="block text-xs text-slate-400">
        Pertanyaan
        <textarea name="question_text" rows="4" required maxlength="5000" placeholder="Tuliskan pertanyaan..."
            class="mt-2 block w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">{{ $question ? $question->question_text : old('question_text') }}</textarea>
    </label>
</div>
