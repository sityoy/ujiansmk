@extends('layouts.tryout')

@section('title', 'Simulasi Tampilan Peserta')
@section('portal-label', $trial ? 'Pratinjau Ujian Uji Coba' : 'Pratinjau Ujian Inti')

@inject('optionOrder', 'App\Services\Exams\StudentOptionOrder')

@section('content')
    @php
        $shuffleOptions = $optionOrder->shouldShuffle($subject);
    @endphp
    <section class="mb-6 rounded-2xl border border-cyan-400/30 bg-cyan-400/[0.045] p-5">
        <div class="flex flex-wrap gap-4 text-sm font-semibold text-cyan-300">
            <a href="{{ $backUrl }}">← Kembali ke bank soal</a>
            @if ($focusedQuestion)
                <a href="{{ $fullPreviewUrl }}">Simulasikan seluruh soal</a>
            @endif
        </div>
        <h1 class="mt-3 text-xl font-semibold text-white">Simulasi tampilan peserta</h1>
        @if ($questions->isNotEmpty())
            <p class="mt-2 text-sm text-slate-300">
                {{ $focusedQuestion ? 'Soal nomor '.$focusedNumber.' pada tampilan peserta.' : $questions->count().' soal tersimpan dari bank yang dipilih.' }}
                Jawaban yang dicoba di sini tidak masuk ke hasil ujian.
            </p>
        @endif
    </section>

    @if ($questions->isEmpty())
        <p class="rounded-xl border border-white/10 p-5 text-slate-400">Tambahkan soal untuk melihat pratinjau peserta.</p>
    @else
        <div id="preview-toolbar" class="exam-toolbar sticky top-0 z-10 mb-6 grid grid-cols-2 gap-4 rounded-2xl border border-white/10 bg-slate-950/95 p-4 shadow-xl lg:grid-cols-[1fr_1fr_auto]">
            <div class="min-w-0">
                <p class="text-xs text-cyan-300">{{ $trial ? 'Peserta Uji Coba' : 'Peserta' }}</p>
                <p class="mt-1 font-semibold text-white">Mode pratinjau</p>
                <p class="text-xs text-slate-400">Kelas {{ $gradeLevel }}</p>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-cyan-300">Mata Pelajaran</p>
                <p class="mt-1 font-semibold text-white">{{ $subject->name }}</p>
                <p class="text-xs text-slate-400">{{ $examName }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">Sisa waktu</p>
                <p class="mt-1 font-mono text-cyan-300">—</p>
            </div>
            <x-exam-text-size />
        </div>

        <div id="exam-content">
            @php
                $number = 0;
                $questionSections = \App\Services\Exams\StudentQuestionSections::fromQuestions($questions);
            @endphp
            @foreach ($questionSections as $section)
                @php
                    $type = $section['type'];
                @endphp
                <section data-exam-section="{{ $type->value }}" data-question-count="{{ $section['questions']->count() }}"
                    aria-labelledby="preview-section-{{ $type->value }}" class="mb-6 space-y-5">
                    <h2 id="preview-section-{{ $type->value }}" tabindex="-1" data-exam-section-heading class="text-lg font-semibold text-white">
                        {{ $type->label() }} <span class="text-sm font-normal text-slate-400">· Bagian {{ $loop->iteration }} dari {{ $loop->count }}</span>
                    </h2>
                    @foreach ($section['questions'] as $question)
                        @php($number++)
                        @php($contextRange = $section['contexts'][$question->question_context_id] ?? null)
                        @php($contextAnchor = 'exam-context-'.$type->value.'-'.$question->question_context_id)
                        @if ($contextRange && $question->context && $contextRange['first'] === $number && ! $focusedQuestion)
                            <x-question-context-block :context="$question->context" :label="$contextRange['label']" :id="$contextAnchor" class="scroll-mt-32" />
                        @endif
                        <x-exam-question-card :question="$question" :number="$focusedNumber ?? $number" :total="$focusedQuestion ? null : $questions->count()"
                            :show-context="$focusedQuestion !== null"
                            :context-reference="$contextRange && $contextRange['first'] !== $number && ! $focusedQuestion ? ['id' => $contextAnchor, 'label' => $contextRange['label']] : null"
                            :relabel-options="$shuffleOptions"
                            :options="$type->requiresManualGrading() ? [] : $optionOrder->forPreview($question, $shuffleOptions)"
                            :name="'preview_'.$question->id" :trial="$trial" />
                    @endforeach
                    @include('components.exam-section-navigation')
                </section>
            @endforeach
            <div id="preview-submit-panel" class="space-y-3">
                <button type="button" id="preview-finish" class="w-full rounded-xl bg-emerald-400 px-5 py-4 font-semibold text-slate-950">Kumpulkan jawaban</button>
                <p id="preview-status" role="status" class="text-sm text-slate-300"></p>
            </div>
        </div>
        <x-exam-image-dialog />
        <script src="{{ asset('js/exam-submission.js') }}?v=8"></script>
        <script>
            (() => {
                const answerSelector = '[data-preview-answer]';
                document.querySelectorAll(answerSelector + '[type="checkbox"]').forEach(input => {
                    input.addEventListener('change', () => examAnswerValue(input));
                });
                window.bindExamSections({
                    sections: [...document.querySelectorAll('[data-exam-section]')],
                    submitPanel: document.getElementById('preview-submit-panel'),
                    toolbar: document.getElementById('preview-toolbar'),
                    answerSelector,
                    flushAnswers: () => Promise.resolve(),
                    pending: new Set(),
                });
                document.getElementById('preview-finish').addEventListener('click', () => {
                    const questions = document.querySelectorAll('[data-exam-question-number]');
                    const unanswered = unansweredExamQuestionNumbers(questions, answerSelector);
                    document.getElementById('preview-status').textContent = unanswered.length > 0
                        ? unansweredExamMessage(unanswered)
                        : 'Simulasi selesai.';
                });
            })();
        </script>
    @endif
@endsection
