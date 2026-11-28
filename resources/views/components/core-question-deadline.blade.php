@props(['period'])

@if ($deadline = $period->core_question_deadline_at?->timezone(\App\Models\AssessmentPeriod::CORE_QUESTION_TIMEZONE))
    @php($passed = $period->coreQuestionDeadlinePassed())
    <p {{ $attributes->class([$passed ? 'text-amber-300' : 'text-cyan-200']) }}>
        Batas pembuatan soal <time datetime="{{ $deadline->toIso8601String() }}">{{ $deadline->format('d/m/Y H:i') }} WIB</time>
        @if ($passed) <span class="font-semibold">· Ditutup</span> @endif
    </p>
@endif
