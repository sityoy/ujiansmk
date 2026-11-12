@props(['question', 'multipleChoice' => true])

<div {{ $attributes->class('space-y-3 text-sm leading-7 text-white') }}>
    @if ($multipleChoice && $question->statement !== null)
        <x-question-text :text="$question->statement" :format="$question->text_format" />
    @endif
    @if (! $multipleChoice)
        <x-question-text :text="$question->question_text" :format="$question->text_format" />
    @endif
    @if ($question->question_image_path)
        @php($isImage = in_array(strtolower(pathinfo($question->question_image_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true))
        @if ($isImage)
            <img src="{{ Storage::url($question->question_image_path) }}" alt="Gambar soal {{ $question->position }}"
                loading="lazy" class="max-h-[32rem] w-auto max-w-full rounded-xl border border-white/10 bg-white object-contain p-2">
        @else
            <a href="{{ Storage::url($question->question_image_path) }}" target="_blank" rel="noopener"
                class="inline-flex rounded-xl border border-cyan-400/30 px-3 py-2 text-xs font-semibold text-cyan-300">Buka file soal</a>
        @endif
    @endif
    @if ($multipleChoice)
        <x-question-text :text="$question->question_text" :format="$question->text_format" />
    @endif
</div>
