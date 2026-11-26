@props(['action', 'questionTypes', 'question' => null, 'backUrl' => null, 'trial' => false, 'availableContexts'])

@php
    $typeValue = old('question_type', $question?->question_type->value ?? $questionTypes[0]->value);
    $format = old('text_format', $question?->text_format ?? 'plain');
    $multipleChoice = $typeValue === 'multiple_choice';
    $keys = (array) old('correct_answers', $errors->has('correct_answers') ? [] : str_split($question?->correct_answer ?? ''));
    $points = old('points', $question?->question_type->requiresManualGrading() ? $question->points : null);
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data"
    data-question-bank-form class="space-y-4">
    @csrf
    @if ($question) @method('PUT') @endif
    <label class="block text-sm text-slate-300">Jenis soal
        <select name="question_type" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
            @foreach ($questionTypes as $type)
                <option value="{{ $type->value }}" @selected($typeValue === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
    </label>
    <x-question-context-fields :question="$question" :contexts="$availableContexts" :format="$format" />
    <x-question-prompt-fields :question="$question" />
    <div data-question-choice-fields @if (! $multipleChoice) hidden @endif class="space-y-3">
        @foreach (['A', 'B', 'C', 'D', 'E'] as $option)
            @php($optionName = 'option_'.strtolower($option))
            <div class="space-y-2 rounded-xl border border-white/10 p-3">
                <x-question-text-editor :name="$optionName" :value="old($optionName, $question?->options[$option] ?? '')"
                    :format="$format" label="Pilihan {{ $option }}" :rows="2" :limit="2000"
                    :required="$multipleChoice" :disabled="! $multipleChoice" placeholder="Pilihan {{ $option }}" />
                <x-question-file-input :name="'option_file_'.strtolower($option)" :label="'Lampiran pilihan '.$option"
                    :alt="'Gambar pilihan '.$option" :path="$question?->option_file_paths[$option] ?? null"
                    :disabled="! $multipleChoice" class="w-full text-xs text-slate-400" />
            </div>
        @endforeach
        <fieldset class="space-y-2 rounded-xl border border-white/10 p-3">
            <legend class="px-1 text-sm font-semibold text-slate-200">Kunci jawaban</legend>
            <p class="text-sm text-slate-400">Centang satu kunci untuk PG biasa. Centang beberapa jika soal memiliki lebih dari satu jawaban benar.</p>
            <div class="flex flex-wrap gap-2">
                @foreach (['A', 'B', 'C', 'D', 'E'] as $option)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-white/10 px-3 py-2 text-sm text-slate-200">
                        <input type="checkbox" name="correct_answers[]" value="{{ $option }}" @checked(in_array($option, $keys, true)) @disabled(! $multipleChoice) class="accent-cyan-500">
                        {{ $option }}
                    </label>
                @endforeach
            </div>
            @error('correct_answers') <p class="text-xs text-rose-300" role="alert">Centang minimal satu kunci jawaban.</p> @enderror
        </fieldset>
        <p class="text-xs text-slate-400">PG bernilai maksimal 2 poin per soal. Pada PG kompleks, poin dibagi rata di antara kunci jawaban. Jumlah pilihan siswa dibatasi sesuai jumlah kunci. Lampiran pilihan dapat berupa gambar atau PDF hingga 5 MB.</p>
    </div>
    <label data-question-manual-points @if ($multipleChoice) hidden @endif class="block text-sm text-slate-300">Bobot maksimal soal
        <input type="number" name="points" min="0.01" max="1000" step="0.01" required @disabled($multipleChoice)
            value="{{ $points ?? ($typeValue === 'essay' ? '7' : '3') }}" @if ($points !== null) data-points-edited @endif
            class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
    </label>
    <div class="flex flex-wrap gap-2">
        <button @class(['rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950', 'w-full' => ! $question])>{{ $question ? 'Simpan perubahan' : 'Simpan soal' }}</button>
        @if ($backUrl)
            <a href="{{ $backUrl }}" class="rounded-xl border border-white/10 px-4 py-3 text-sm font-semibold text-slate-300">Batal</a>
        @endif
    </div>
</form>
<script src="{{ asset('js/question-bank-form.js') }}?v=3" defer></script>
