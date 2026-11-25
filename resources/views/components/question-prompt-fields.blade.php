@props(['question' => null, 'multipleChoice' => true])
@php($format = old('text_format', $question?->text_format ?? 'plain'))

<div data-question-prompt-fields class="space-y-3">
    <input type="hidden" name="text_format" value="{{ $format }}">
    <div data-question-statement @if (! $multipleChoice) hidden @endif>
        <x-question-text-editor name="statement" :value="old('statement', $question?->statement)"
            :format="$format" label="Pernyataan (opsional)"
            :disabled="! $multipleChoice" placeholder="Tuliskan bacaan, konteks, atau butir (1), (2), (3)..." />
        <p class="mt-2 text-xs text-slate-400">Gunakan baris terpisah untuk setiap butir bernomor.</p>
    </div>
    <div data-question-attachment>
        <x-question-file-input name="question_image" label="Gambar atau PDF (opsional, maks. 5 MB)" alt="Gambar soal" :path="$question?->question_image_path"
            class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-cyan-400 file:px-3 file:py-2 file:font-semibold file:text-slate-950" />
        <p class="mt-2 text-xs text-slate-400">Soal Bahasa Arab disarankan memakai gambar agar huruf dan harakat tetap rapi.</p>
    </div>
    <div data-question-text>
        <x-question-text-editor name="question_text" :value="old('question_text', $question?->question_text)"
            :format="$format" label="Pertanyaan"
            required placeholder="Tuliskan pertanyaan..." />
    </div>
</div>
