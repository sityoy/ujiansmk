@props(['action', 'questionTypes', 'question' => null, 'backUrl' => null, 'trial' => false])

@php
    $typeValue = old('question_type', $question?->question_type->value ?? $questionTypes[0]->value);
    $format = old('text_format', $question?->text_format ?? 'plain');
    $multipleChoice = $typeValue === 'multiple_choice';
    $points = old('points', $question?->question_type->requiresManualGrading() ? $question->points : null);
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data"
    data-question-bank-form @if (! $trial) data-core-question-form @endif class="space-y-4">
    @csrf
    @if ($question) @method('PUT') @endif
    <label class="block text-sm text-slate-300">Jenis soal
        <select name="question_type" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
            @foreach ($questionTypes as $type)
                <option value="{{ $type->value }}" @selected($typeValue === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
    </label>
    <x-question-prompt-fields :question="$question" :multiple-choice="$trial || $multipleChoice" />
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
        <label class="block text-sm text-slate-300">Kunci jawaban
            <select name="correct_answer" @required($multipleChoice) @disabled(! $multipleChoice)
                class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                <option value="">Pilih jawaban benar</option>
                @foreach (['A', 'B', 'C', 'D', 'E'] as $option)
                    <option value="{{ $option }}" @selected(old('correct_answer', $question?->correct_answer) === $option)>Pilihan {{ $option }}</option>
                @endforeach
            </select>
        </label>
        <p class="text-xs text-slate-400">Setiap jawaban PG yang benar bernilai 2 poin. Lampiran pilihan dapat berupa gambar atau PDF hingga 5 MB.</p>
    </div>
    <label data-question-manual-points @if ($multipleChoice) hidden @endif class="block text-sm text-slate-300">Bobot maksimal soal
        <input type="number" name="points" min="0.01" max="1000" step="0.01" required @disabled($multipleChoice)
            value="{{ $points ?? ($typeValue === 'essay' ? '7' : '3') }}" @if ($points !== null) data-points-edited @endif
            class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
    </label>
    <div class="flex flex-wrap gap-2">
        <button class="rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950">{{ $question ? 'Simpan perubahan' : 'Simpan soal' }}</button>
        @if ($backUrl)
            <a href="{{ $backUrl }}" class="rounded-xl border border-white/10 px-4 py-3 text-sm font-semibold text-slate-300">Batal</a>
        @endif
    </div>
</form>
<script src="{{ asset('js/question-bank-form.js') }}" defer></script>
