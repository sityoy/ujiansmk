@extends('layouts.app')

@section('title', 'Mengerjakan Ujian')
@section('eyebrow', 'Ujian Berlangsung')
@section('heading', $attempt->assignment->assessmentSubject->subject->name)

@inject('optionOrder', 'App\Services\Exams\StudentOptionOrder')

@section('content')
    @php($shuffleOptions = $optionOrder->shouldShuffle($attempt->assignment->assessmentSubject->subject))
    @if ($attempt->security_enabled)
        <div id="exam-security" data-state-url="{{ route('student.exams.security', $attempt) }}"
            data-event-url="{{ route('student.exams.incident', $attempt) }}"
            data-index-url="{{ route('student.exams.result', $attempt) }}" data-attempt-id="{{ $attempt->id }}"
            data-count="{{ $attempt->violation_count }}" data-locked="0">
            <div id="security-panel" role="dialog" aria-modal="true" aria-labelledby="security-title" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/95 p-3 backdrop-blur sm:p-6">
                <section class="mx-auto mt-3 max-w-xl rounded-3xl border border-amber-400/30 bg-slate-900 p-4 shadow-xl sm:mt-12 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">Pengawasan ujian · batas 2 pelanggaran</p>
                        <x-theme-picker />
                    </div>
                    <h2 id="security-title" class="mt-4 text-2xl font-semibold">Siap masuk mode ujian?</h2>
                    <p id="security-message" aria-live="polite" class="mt-4 text-sm leading-7 text-slate-300">Tetap di halaman ujian dan gunakan layar penuh jika didukung. Jangan berpindah aplikasi atau tab, menarik bilah notifikasi, maupun mengubah zoom browser.</p>
                    <p class="mt-3 text-sm leading-6 text-slate-300">Jika ada gambar soal atau pilihan jawaban, ketuk Perbesar / Perkecil gambar di bawah gambar tersebut.</p>
                    <p class="mt-3 text-sm leading-6 text-amber-200">Waktu sedang berjalan, termasuk saat peringatan muncul atau ujian terkunci. Jika ada masalah, panggil pengawas dan biarkan ujian tetap terbuka.</p>
                    @php($pgCount = $questions->filter(fn ($question) => $question->question_type->value === 'multiple_choice')->count())
                    @if ($pgCount > 0)<p class="mt-3 rounded-xl border border-cyan-400/20 bg-cyan-400/[0.06] p-3 text-sm text-cyan-100">Setiap soal PG bernilai maksimal <strong>2 poin</strong>. Pada PG kompleks, poin dibagi rata sesuai jumlah jawaban benar pada kunci. Nilai PG maksimal 100.</p>@endif
                    <x-exam-device-preparation />
                    <button id="security-continue" type="button" class="mt-6 w-full rounded-xl bg-cyan-400 px-5 py-3 font-semibold text-slate-950">Saya paham, masuk mode ujian</button>
                    <p id="security-network" aria-live="polite" class="mt-3 text-sm text-amber-300"></p>
                </section>
            </div>
        </div>
    @endif
    <div class="exam-toolbar sticky top-3 z-20 mb-6 grid grid-cols-2 gap-4 rounded-2xl border border-cyan-400/30 bg-slate-950/95 px-5 py-4 shadow-xl backdrop-blur lg:grid-cols-[auto_1fr_1fr_auto] lg:items-center">
        <div>
            <p class="text-xs text-slate-500">Sisa waktu</p>
            <p id="exam-timer" class="mt-1 font-mono text-xl font-semibold text-cyan-300">--:--:--</p>
            <p class="mt-1 text-xs text-slate-400">Batas pengumpulan <time datetime="{{ $deadline->toIso8601String() }}">{{ $deadline->format('H:i') }}</time></p>
        </div>
        <div class="min-w-0 lg:border-l lg:border-white/10 lg:pl-4">
            <p class="text-[0.65rem] font-semibold uppercase tracking-widest text-cyan-300">Peserta</p>
            <p class="mt-1 truncate text-sm font-semibold text-white">{{ $student->full_name }}</p>
            <p class="mt-1 text-xs text-slate-400">
                NIS/NISN: {{ collect([$student->student_number, $student->nisn])->filter()->join(' / ') ?: '-' }}
                · Kelas {{ $student->schoolClass?->name ?? '-' }}
            </p>
        </div>
        <div class="min-w-0 lg:border-l lg:border-white/10 lg:pl-4">
            <p class="text-[0.65rem] font-semibold uppercase tracking-widest text-cyan-300">Mata Pelajaran</p>
            <p class="mt-1 truncate text-sm font-semibold text-white">{{ $attempt->assignment->assessmentSubject->subject->name }}</p>
            <p class="mt-1 truncate text-xs text-slate-400">{{ $attempt->assignment->assessmentSubject->assessmentPeriod->name }}</p>
        </div>
        <div class="text-right">
            <p id="save-status" class="text-xs text-slate-400">Jawaban tersimpan otomatis</p>
            <button id="retry-save" type="button" hidden class="mt-2 text-xs font-semibold text-amber-300">Coba simpan lagi</button>
            <p class="mt-1 text-xs text-amber-300">Jangan menutup halaman ujian</p>
            @if ($attempt->security_enabled)<p id="security-count" class="mt-1 text-xs text-rose-300">Pelanggaran {{ $attempt->violation_count }}/2</p>@endif
        </div>
        <x-exam-text-size />
    </div>

    <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
        <x-school-logo :school="$schoolProfile" custom-only class="mb-3 rounded-lg bg-white p-1" width="44" height="44" />
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">{{ $attempt->assignment->assessmentSubject->assessmentPeriod->name }}</p>
        <h2 class="mt-2 text-2xl font-semibold text-white">{{ $attempt->assignment->assessmentSubject->subject->name }}</h2>
        @php($session = $attempt->assignment->examSession)
        <p class="mt-2 text-sm text-slate-400">{{ $session->formattedWindow() }} · {{ $session->duration_minutes }} menit · {{ $questions->count() }} soal</p>
    </section>

    <fieldset id="exam-content" @disabled($attempt->security_enabled) @if($attempt->security_enabled) hidden @endif>
        @error('submission')
            <p role="alert" class="mb-3 text-sm text-rose-300">{{ $message }}</p>
        @enderror
        @php($number = 0)
        @foreach ($questionSections as $section)
            @php($type = $section['type'])
            <section data-exam-section="{{ $type->value }}" data-question-count="{{ $section['questions']->count() }}"
                aria-labelledby="exam-section-{{ $type->value }}" class="mt-6 space-y-4">
                <h2 id="exam-section-{{ $type->value }}" tabindex="-1" class="text-lg font-semibold text-white" data-exam-section-heading>
                    {{ $type->label() }} <span class="text-sm font-normal text-slate-400">· Bagian {{ $loop->iteration }} dari {{ $loop->count }}</span>
                </h2>
                @foreach ($section['questions'] as $question)
                    @php($number++)
                    @php($contextRange = $section['contexts'][$question->question_context_id] ?? null)
                    @php($contextAnchor = 'exam-context-'.$type->value.'-'.$question->question_context_id)
                    @if ($contextRange && $question->context && $contextRange['first'] === $number)
                        <x-question-context-block :context="$question->context" :label="$contextRange['label']" :id="$contextAnchor" class="scroll-mt-32" />
                    @endif
                    <x-exam-question-card :question="$question" :number="$number" :total="$questions->count()"
                        :show-context="false"
                        :relabel-options="$shuffleOptions"
                        :context-reference="$contextRange && $contextRange['first'] !== $number ? ['id' => $contextAnchor, 'label' => $contextRange['label']] : null"
                        :options="$type->requiresManualGrading() ? [] : $optionOrder->forQuestion($attempt, $question, $shuffleOptions)"
                        :name="'question_'.$question->id" :answer="$answers->get($question->id)?->answer"
                        :save-url="route('student.exams.answer', [$attempt, $question])" />
                @endforeach
                @include('components.exam-section-navigation')
            </section>
        @endforeach

        <form id="submit-exam" method="POST" action="{{ route('student.exams.submit', $attempt) }}" data-question-count="{{ $questions->count() }}" class="mt-6">
            @csrf
            <x-exam-submit-confirmation :show-server-error="false" />
            <button type="submit" class="w-full rounded-xl bg-emerald-400 px-5 py-4 font-semibold text-slate-950">Selesai dan kumpulkan jawaban</button>
        </form>
        <x-exam-image-dialog />
    </fieldset>

    <script src="{{ asset('js/exam-submission.js') }}?v=8"></script>
    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        // Base the countdown on server time, not the student's system clock.
        const remainingAtLoad = {{ max(0, $deadline->getTimestamp() - now()->getTimestamp()) * 1000 }};
        const loadedAt = performance.now();
        const timer = document.getElementById('exam-timer');
        const status = document.getElementById('save-status');
        const submitForm = document.getElementById('submit-exam');
        const retryButton = document.getElementById('retry-save');
        const inputs = document.querySelectorAll('[data-save-url]');
        const pending = new Map();
        let saving = null;
        window.examAutoSubmit = false;

        const flushAnswers = () => {
            if (saving) return saving;
            saving = (async () => {
                while (pending.size && !window.examAutoSubmit && !window.examSecurity?.isBlocked()) {
                    const [url, answer] = pending.entries().next().value;
                    status.textContent = `Menyimpan jawaban (${pending.size} tertunda)...`;
                    const controller = typeof AbortController === 'function' ? new AbortController() : null;
                    let timeout;
                    const expired = new Promise((_, reject) => {
                        timeout = setTimeout(() => { controller?.abort(); reject(new Error('Koneksi terlalu lambat.')); }, 15000);
                    });
                    try {
                        const request = fetch(url, {
                            method: 'PUT',
                            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                            body: JSON.stringify({answer}),
                            ...(controller ? {signal: controller.signal} : {}),
                        }).then(async response => ({response, result: await response.json().catch(() => ({}))}));
                        const {response, result} = await Promise.race([request, expired]);
                        if (result.errors?.security) window.examSecurity?.check();
                        if (!response.ok) throw new Error(result.message || 'Jawaban gagal disimpan. Periksa koneksi atau sesi login.');
                        if (pending.get(url) === answer) pending.delete(url);
                        status.textContent = `Tersimpan pukul ${result.saved_at}`;
                        status.classList.remove('text-rose-300');
                        retryButton.hidden = true;
                    } catch (error) {
                        status.textContent = `${error.name === 'AbortError' ? 'Koneksi terlalu lambat.' : error.message} ${pending.size} jawaban belum tersimpan. Klik coba simpan lagi.`;
                        status.classList.add('text-rose-300');
                        retryButton.hidden = false;
                        break;
                    } finally {
                        clearTimeout(timeout);
                    }
                }
            })().finally(() => { saving = null; });
            return saving;
        };

        const queueAnswer = (input) => {
            if (window.examSecurity?.isBlocked()) return;
            const answer = examAnswerValue(input);
            if (answer === null) return;
            pending.set(input.dataset.saveUrl, answer);
            flushAnswers();
        };
        const textTimers = new Map();
        inputs.forEach((input) => {
            input.addEventListener('change', () => queueAnswer(input));
            if (input.tagName === 'TEXTAREA') {
                input.addEventListener('input', () => {
                    if (window.examSecurity?.isBlocked()) return;
                    pending.set(input.dataset.saveUrl, input.value);
                    clearTimeout(textTimers.get(input));
                    textTimers.set(input, setTimeout(flushAnswers, 900));
                });
            }
        });
        retryButton.addEventListener('click', flushAnswers);
        window.addEventListener('online', flushAnswers);
        window.addEventListener('examsecurity:ready', flushAnswers);
        window.addEventListener('beforeunload', (event) => {
            if (pending.size && !window.examAutoSubmit) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        window.bindExamSections({
            sections: [...document.querySelectorAll('[data-exam-section]')], submitPanel: submitForm, flushAnswers, pending,
            toolbar: document.querySelector('.exam-toolbar'),
        });
        window.bindExamSubmission({ form: submitForm, inputs, flushAnswers, pending });

        const updateTimer = () => {
            const clock = window.examServerClock;
            const remaining = Math.max(0, clock ? clock.remainingMs - (performance.now() - clock.at) : remainingAtLoad - (performance.now() - loadedAt));
            const seconds = Math.floor(remaining / 1000);
            const hours = String(Math.floor(seconds / 3600)).padStart(2, '0');
            const minutes = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
            const secs = String(seconds % 60).padStart(2, '0');
            timer.textContent = `${hours}:${minutes}:${secs}`;

            if (remaining <= 0 && !window.examAutoSubmit) {
                window.examAutoSubmit = true;
                status.textContent = 'Waktu habis, mengumpulkan jawaban...';
                submitForm.submit();
            }
        };
        updateTimer();
        setInterval(updateTimer, 1000);

    </script>
    @if ($attempt->security_enabled)
        <script src="{{ asset('js/exam-security.js') }}?v=20261119-1" defer></script>
    @else
        <script>
            document.addEventListener('visibilitychange', () => {
                if (document.hidden && !window.examAutoSubmit) {
                    fetch(@json(route('student.exams.incident', $attempt)), {
                        method: 'POST', keepalive: true,
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                        body: JSON.stringify({category: 'tab_hidden'}),
                    }).catch(() => {});
                }
            });
        </script>
    @endif
@endsection
