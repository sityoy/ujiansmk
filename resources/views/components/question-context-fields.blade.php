@props(['question' => null, 'contexts', 'format'])

@inject('questionText', 'App\Services\Exams\QuestionText')

@php
    $context = $question?->context;
    $previous = session('saved_context_id');
    $selection = (string) old('question_context', $context?->id ?? ($contexts->contains('id', $previous) ? $previous : ''));
    $editSelected = $selection === 'edit';
    $showFields = $editSelected || $selection === 'new';
    $contextFormat = old('text_format', $context?->text_format ?? $format);
@endphp

<div class="space-y-3 rounded-xl border border-cyan-400/30 p-3">
    <label class="block text-sm font-semibold text-cyan-300">Pernyataan atau gambar bersama
        <select name="question_context" data-context-select class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm">
            <option value="" @selected($selection === '')>Tanpa pernyataan bersama</option>
            <option value="new" @selected($selection === 'new')>Buat pernyataan bersama baru</option>
            @if ($context)
                <option value="edit" @selected($editSelected)>Edit pernyataan bersama untuk semua soal terkait</option>
            @endif
            @foreach ($contexts as $item)
                @php($description = \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($questionText->plainText($item->statement, $item->text_format)), 70))
                <option value="{{ $item->id }}" @selected($selection === (string) $item->id)>Kelompok {{ $item->id }} · {{ $description ?: 'Lampiran gambar/PDF' }}</option>
            @endforeach
        </select>
    </label>
    <p class="text-xs leading-5 text-slate-400">Buat kelompok saat menulis soal pertama. Pilih kelompok yang sama pada soal berikutnya. Pilih Buat atau Edit pernyataan bersama untuk mengunggah gambar bagi seluruh soal dalam kelompok tersebut.</p>
    <div data-context-fields @if (! $showFields) hidden @endif data-saved-caption="{{ $context?->image_caption }}" class="space-y-3">
        <x-question-text-editor name="context_statement"
            :value="old('context_statement', $context?->statement)"
            :format="$contextFormat" label="Pernyataan bersama" :disabled="! $showFields"
            placeholder="Tulis bacaan, daftar pernyataan, atau petunjuk untuk beberapa soal." />
        <x-question-file-input name="context_image" label="Gambar bersama untuk kelompok ini (gambar/PDF, maks. 5 MB)"
            alt="Gambar pernyataan bersama" :path="$context?->image_path" :show-saved="$editSelected"
            :caption="$editSelected ? $context?->image_caption : null" :disabled="! $showFields" />
    </div>
    @if ($errors->hasAny(['question_context', 'context_statement', 'context_image', 'context_image_caption']))
        <p class="text-xs text-rose-300" role="alert">{{ $errors->first(['question_context', 'context_statement', 'context_image', 'context_image_caption']) }}</p>
    @endif
</div>
