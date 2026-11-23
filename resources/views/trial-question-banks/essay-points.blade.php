<label data-trial-essay-points class="block text-sm text-slate-300">
    Bobot esai
    <input type="number" name="points" min="0.01" max="1000" step="0.01" required
        value="{{ $question?->question_type->value === 'essay' ? $question->points : ($question ? 5 : old('points', 5)) }}"
        class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
</label>
