@props(['question', 'multipleChoice' => true])

<div {{ $attributes->class('space-y-3 text-sm leading-7 text-white') }}>
    @if ($multipleChoice && $question->statement !== null)
        <x-question-text :text="$question->statement" :format="$question->text_format" />
    @endif
    @if (! $multipleChoice)
        <x-question-text :text="$question->question_text" :format="$question->text_format" />
    @endif
    @if ($question->question_image_path)
        <x-question-attachment :path="$question->question_image_path" :alt="'Gambar soal '.$question->position" link-label="Buka file soal" />
    @endif
    @if ($multipleChoice)
        <x-question-text :text="$question->question_text" :format="$question->text_format" />
    @endif
</div>
