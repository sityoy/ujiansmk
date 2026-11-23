@php($questionType = $question?->question_type->value ?? old('question_type', 'multiple_choice'))
<label class="block text-sm text-slate-300">
    Jenis soal
    <select name="question_type" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
        @foreach (\App\Enums\ExamQuestionType::cases() as $type)
            <option value="{{ $type->value }}" @selected($questionType === $type->value)>{{ $type->label() }}</option>
        @endforeach
    </select>
</label>
