@extends('layouts.tryout')

@section('title', $trialExam->title)

@inject('optionOrder', 'App\Services\Exams\StudentOptionOrder')

@section('content')
    @php($shuffleOptions = $optionOrder->shouldShuffle($trialExam->questionBank?->subject))
    <div id="exam-security"
        data-state-url="{{ route('tryout.security', [$trialExam, $attempt]) }}"
        data-event-url="{{ route('tryout.incident', [$trialExam, $attempt]) }}"
        data-index-url="{{ route('tryout.result', [$trialExam, $attempt]) }}"
        data-attempt-id="trial-{{ $attempt->id }}"
        data-count="{{ $attempt->violation_count }}"
        data-locked="{{ $attempt->security_locked_at ? '1' : '0' }}">
        <div id="security-panel" role="dialog" aria-modal="true" aria-labelledby="security-title" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/95 p-3 backdrop-blur sm:p-6">
            <section class="mx-auto mt-3 max-w-xl rounded-3xl border border-amber-400/30 bg-slate-900 p-4 shadow-xl sm:mt-12 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">Pengawasan uji coba · batas 2 pelanggaran</p>
                    <x-theme-picker />
                </div>
                <h2 id="security-title" class="mt-4 text-2xl font-semibold">Siap masuk mode ujian?</h2>
                <p id="security-message" aria-live="polite" class="mt-4 text-sm leading-7 text-slate-300">Pengamanan sama dengan Ujian Inti. Tetap di halaman ujian dan gunakan layar penuh jika didukung. Jangan berpindah aplikasi atau tab, menarik bilah notifikasi, maupun mengubah zoom browser.</p>
                <p class="mt-3 text-sm leading-6 text-slate-300">Jika ada gambar soal atau pilihan jawaban, ketuk Perbesar / Perkecil gambar di bawah gambar tersebut.</p>
                <p class="mt-3 text-sm leading-6 text-amber-200">Waktu sedang berjalan, termasuk saat peringatan muncul atau ujian terkunci. Jika ada masalah, panggil pengawas dan biarkan ujian tetap terbuka.</p>
                @if (($questionSections[0]['type']->value ?? null) === 'multiple_choice')
                    <p class="mt-3 rounded-xl border border-cyan-400/20 bg-cyan-400/[0.06] p-3 text-sm text-cyan-100">Setiap soal PG bernilai maksimal <strong>2 poin</strong>. Pada PG kompleks, poin dibagi rata sesuai jumlah jawaban benar pada kunci. Nilai PG maksimal 100.</p>
                @endif
                <x-exam-device-preparation />
                <button id="security-continue" type="button" class="mt-6 w-full rounded-xl bg-cyan-400 px-5 py-3 font-semibold text-slate-950">Saya paham, masuk mode ujian</button>
                <p id="security-network" aria-live="polite" class="mt-3 text-sm text-amber-300"></p>
            </section>
        </div>
    </div>

    <form id="trial-form" method="POST" action="{{ route('tryout.submit', [$trialExam, $attempt]) }}" data-question-count="{{ $questions->count() }}">
        @csrf
        <div class="exam-toolbar sticky top-0 z-10 mb-6 grid grid-cols-2 gap-4 rounded-2xl border border-white/10 bg-slate-950/95 p-4 shadow-xl lg:grid-cols-[1fr_1fr_auto_auto] lg:items-center">
            <div class="min-w-0">
                <p class="text-[0.65rem] font-semibold uppercase tracking-widest text-cyan-300">Peserta Uji Coba</p>
                <p class="mt-1 truncate font-semibold text-white">{{ $attempt->participant_name }}</p>
                <p class="mt-1 text-xs text-slate-400">Nomor peserta: {{ $attempt->participant_identifier ?: '-' }}</p>
            </div>
            <div class="min-w-0 lg:border-l lg:border-white/10 lg:pl-4">
                <p class="text-[0.65rem] font-semibold uppercase tracking-widest text-cyan-300">Mata Pelajaran</p>
                <p class="mt-1 truncate font-semibold text-white">{{ $trialExam->questionBank->subject->name }}</p>
                <p class="mt-1 truncate text-xs text-slate-400">{{ $trialExam->title }} · {{ $questions->count() }} soal</p>
            </div>
            <div class="text-right">
                <p class="text-[0.65rem] font-semibold uppercase tracking-widest text-slate-500">Sisa waktu</p>
                <span id="timer" class="mt-1 inline-flex rounded-xl bg-amber-400/10 px-3 py-2 font-mono text-amber-200">--:--</span>
                <p id="save-status" class="mt-2 text-xs text-slate-500">Jawaban tersimpan otomatis</p>
                <button id="retry-save" type="button" hidden class="mt-2 rounded-lg border border-rose-300/30 px-3 py-2 text-xs text-rose-200">Coba simpan lagi</button>
            </div>
            <div class="flex items-center justify-end">
                <span id="security-count" class="rounded-xl bg-rose-400/10 px-3 py-2 text-xs text-rose-200">Pelanggaran {{ $attempt->violation_count }}/2</span>
            </div>
            <x-exam-text-size />
        </div>

        <fieldset id="exam-content" disabled hidden>
            @error('submission')
                <p role="alert" class="mb-3 text-sm text-rose-300">{{ $message }}</p>
            @enderror
            @php($number = 0)
            @foreach ($questionSections as $section)
                @php($type = $section['type'])
                <section data-exam-section="{{ $type->value }}" data-question-count="{{ $section['questions']->count() }}"
                    aria-labelledby="trial-section-{{ $type->value }}" class="mb-6 space-y-5">
                    <h2 id="trial-section-{{ $type->value }}" tabindex="-1" class="text-lg font-semibold text-white" data-exam-section-heading>
                        {{ $type->label() }}
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
                            :name="'answers['.$question->id.']'" :answer="$answers->get($question->id)?->answer"
                            :save-url="route('tryout.answer', [$trialExam, $attempt, $question])" trial />
                    @endforeach
                    @include('components.exam-section-navigation')
                </section>
            @endforeach
            <div id="trial-submit-panel" class="mt-6">
                <x-exam-submit-confirmation :show-server-error="false" />
                <button type="submit" class="w-full rounded-xl bg-cyan-400 px-4 py-4 font-semibold text-slate-950">Kumpulkan jawaban</button>
            </div>
            <x-exam-image-dialog />
        </fieldset>
    </form>

    <script src="{{ asset('js/exam-submission.js') }}?v=8"></script>
    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const remainingAtLoad = {{ max(0, $deadline->getTimestamp() - now()->getTimestamp()) * 1000 }};
        const loadedAt = performance.now();
        const form = document.getElementById('trial-form');
        const timer = document.getElementById('timer');
        const saveStatus = document.getElementById('save-status');
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
                    saveStatus.textContent = 'Menyimpan jawaban...';
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
                        if (!response.ok) throw new Error(result.message || 'Jawaban gagal disimpan.');
                        if (pending.get(url) === answer) pending.delete(url);
                        saveStatus.textContent = 'Tersimpan pukul ' + result.saved_at;
                        saveStatus.classList.remove('text-rose-300');
                        retryButton.hidden = true;
                    } catch (error) {
                        saveStatus.textContent = error.message + ' Jawaban akan dicoba lagi.';
                        saveStatus.classList.add('text-rose-300');
                        retryButton.hidden = false;
                        break;
                    } finally {
                        clearTimeout(timeout);
                    }
                }
            })().finally(() => { saving = null; });
            return saving;
        };

        const textTimers = new Map();
        inputs.forEach(input => {
            const queueAnswer = () => {
                if (window.examSecurity?.isBlocked()) return;
                const answer = examAnswerValue(input);
                if (answer === null) return;
                pending.set(input.dataset.saveUrl, answer);
                flushAnswers();
            };
            input.addEventListener('change', queueAnswer);
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

        window.bindExamSections({
            sections: [...document.querySelectorAll('[data-exam-section]')], submitPanel: document.getElementById('trial-submit-panel'),
            flushAnswers, pending, toolbar: document.querySelector('.exam-toolbar'),
        });
        window.bindExamSubmission({ form, inputs, flushAnswers, pending });

        const updateTimer = () => {
            const clock = window.examServerClock;
            const remaining = Math.max(0, clock ? clock.remainingMs - (performance.now() - clock.at) : remainingAtLoad - (performance.now() - loadedAt));
            const seconds = Math.floor(remaining / 1000);
            timer.textContent = String(Math.floor(seconds / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0');
            if (remaining <= 0 && !window.examAutoSubmit) {
                window.examAutoSubmit = true;
                saveStatus.textContent = 'Waktu habis, jawaban dikumpulkan otomatis...';
                form.submit();
            }
        };
        updateTimer();
        setInterval(updateTimer, 1000);
    </script>
    <script src="{{ asset('js/exam-security.js') }}?v=20261119-1" defer></script>
@endsection
