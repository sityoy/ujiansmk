@props(['text' => '', 'format' => 'plain'])

<div {{ $attributes->class('question-rich-text min-w-0 whitespace-pre-wrap') }}>{{ app(App\Services\Exams\QuestionText::class)->render($text, $format) }}</div>
