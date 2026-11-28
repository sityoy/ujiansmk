@props(['question', 'showContext' => true])

<div {{ $attributes->class('space-y-3 text-sm leading-7 text-white') }}>
    @if ($showContext && $question->context)
        <x-question-context-block :context="$question->context" />
    @endif
    @if (filled($question->statement))
        <div class="exam-statement">
            <p class="exam-statement-title">Pernyataan soal</p>
            <x-question-text :text="$question->statement" :format="$question->text_format" />
        </div>
    @endif
    @if ($question->question_image_path)
        <x-question-attachment :path="$question->question_image_path" :caption="$question->question_image_caption" :alt="'Gambar soal '.$question->position" link-label="Buka file soal" />
    @endif
    <x-question-text :text="$question->question_text" :format="$question->text_format" />
</div>
