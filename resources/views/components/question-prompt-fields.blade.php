@props(['question' => null, 'multipleChoice' => true])

<div data-question-prompt-fields class="space-y-3">
    <input type="hidden" name="text_format" value="{{ $question?->text_format ?? old('text_format', 'plain') }}">
    <div data-question-statement @if (! $multipleChoice) hidden @endif>
        <x-question-text-editor name="statement" :value="$question ? $question->statement : old('statement')"
            :format="$question?->text_format ?? old('text_format', 'plain')" label="Pernyataan (opsional)"
            :disabled="! $multipleChoice" placeholder="Tuliskan bacaan, konteks, atau butir (1), (2), (3)..." />
        <p class="mt-2 text-xs text-slate-400">Gunakan baris terpisah untuk setiap butir bernomor.</p>
    </div>
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
    <div data-question-text>
        <x-question-text-editor name="question_text" :value="$question ? $question->question_text : old('question_text')"
            :format="$question?->text_format ?? old('text_format', 'plain')" label="Pertanyaan"
            required placeholder="Tuliskan pertanyaan..." />
    </div>
</div>
