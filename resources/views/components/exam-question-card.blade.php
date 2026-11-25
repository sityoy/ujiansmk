@props(['question', 'number', 'total', 'options', 'name', 'answer' => null, 'saveUrl' => null, 'trial' => false])

@php
    $type = $question->question_type->value;
    $limit = \App\Services\Exams\MultipleChoiceAnswers::selectionLimit($question->correct_answer);
    $multiple = $limit > 1;
    $selected = $type === 'multiple_choice' ? str_split($answer ?? '') : [];
@endphp
<article @class(['border border-white/10 bg-white/[0.035]', 'rounded-3xl p-6' => $trial, 'rounded-2xl p-5' => ! $trial])>
    @if ($trial)
        <div class="flex gap-4">
            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-cyan-400/10 font-semibold text-cyan-300">{{ $number }}</span>
            <div class="min-w-0 flex-1">
    @else
        <p class="text-xs font-semibold text-cyan-300">Soal {{ $number }}@if ($total !== null) dari {{ $total }}@endif</p>
    @endif
    <x-question-prompt :question="$question" :multiple-choice="$trial || $type === 'multiple_choice'" :class="$trial ? '' : 'mt-3'" />
    @if ($type === 'multiple_choice')
        <fieldset @if ($multiple) data-multiple-answer data-choice-limit="{{ $limit }}" @endif @class(['grid min-w-0', 'mt-5 gap-3' => $trial, 'mt-4 gap-2' => ! $trial])>
            <legend class="mb-2 text-sm font-semibold {{ $multiple ? 'text-cyan-300' : 'text-slate-400' }}">
                {{ $multiple ? 'Pilih 1 sampai '.$limit.' jawaban yang menurut Anda benar.' : 'Pilih satu jawaban.' }}
            </legend>
            @if ($multiple)
                <p data-choice-limit-message @if (count($selected) <= $limit) hidden @endif role="status" class="text-sm text-amber-200">
                    Maksimal {{ $limit }} pilihan. Untuk mengganti jawaban, hapus dulu salah satu centang.
                </p>
            @endif
            @if ($multiple && $trial && $saveUrl)
                <input type="hidden" name="{{ $name }}" value="{{ $answer }}" data-choice-value>
            @endif
            @foreach ($options as $key => $option)
                @php($displayKey = chr(65 + $loop->index))
                @php($optionPath = $question->option_file_paths[$key] ?? null)
                <div @class(['rounded-xl border border-white/10 hover:border-cyan-400/30', 'tryout-option bg-slate-950/50' => $trial,
                    'text-sm text-slate-300 transition hover:bg-cyan-400/5' => ! $trial])>
                    <label class="exam-choice-label flex cursor-pointer items-start gap-3 px-4 py-3">
                        <input type="{{ $multiple ? 'checkbox' : 'radio' }}" name="{{ $multiple ? 'selection_'.$question->id : $name }}" value="{{ $key }}"
                            @if ($saveUrl) data-save-url="{{ $saveUrl }}" @else data-preview-answer @endif
                            @checked(in_array($key, $selected, true)) class="mt-1 accent-cyan-500">
                        <strong class="text-cyan-300">{{ $displayKey }}.</strong>
                        <x-question-text :text="$option" :format="$question->text_format" />
                    </label>
                    @if ($optionPath)
                        <x-exam-option-attachment :path="$optionPath" :label="$displayKey" />
                    @endif
                </div>
            @endforeach
        </fieldset>
    @else
        <label class="mt-4 block text-xs text-slate-400">
            {{ $type === 'short_answer' ? 'Jawaban singkat' : 'Jawaban esai' }} · dikoreksi manual{{ $trial ? '' : ' oleh guru' }}
            <textarea name="{{ $name }}" rows="{{ $type === 'short_answer' ? 3 : 8 }}" maxlength="10000"
                @if ($saveUrl) data-save-url="{{ $saveUrl }}" @else data-preview-answer @endif
                class="exam-written-answer mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-white outline-none focus:border-cyan-400">{{ $answer }}</textarea>
        </label>
    @endif
    @if ($trial)
            </div>
        </div>
    @endif
</article>
