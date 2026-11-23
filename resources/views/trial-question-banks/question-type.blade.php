@php($questionType = $question?->question_type->value ?? old('question_type', 'multiple_choice'))
<label class="block text-sm text-slate-300">
    Jenis soal
    <select name="question_type" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
        <option value="multiple_choice" @selected($questionType === 'multiple_choice')>Pilihan Ganda</option>
        <option value="essay" @selected($questionType === 'essay')>Esai</option>
    </select>
</label>
