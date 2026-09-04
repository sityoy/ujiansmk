@extends('layouts.app')

@section('title', 'Mengerjakan Ujian')
@section('eyebrow', 'Ujian Berlangsung')
@section('heading', $attempt->assignment->assessmentSubject->subject->name)

@section('content')
    @if ($attempt->security_enabled)
        <div id="exam-security" data-state-url="{{ route('student.exams.security', $attempt) }}"
            data-event-url="{{ route('student.exams.incident', $attempt) }}"
            data-index-url="{{ route('student.exams.index') }}" data-attempt-id="{{ $attempt->id }}"
            data-count="{{ $attempt->violation_count }}" data-locked="0">
            <div id="security-panel" role="dialog" aria-modal="true" aria-labelledby="security-title" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/95 p-6 backdrop-blur">
                <section class="mx-auto mt-12 max-w-xl rounded-3xl border border-amber-400/30 bg-slate-900 p-6 shadow-xl">
                    <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">Pengawasan ujian · batas 2 pelanggaran</p>
                    <h2 id="security-title" class="mt-4 text-2xl font-semibold">Siap masuk mode ujian?</h2>
                    <p id="security-message" aria-live="polite" class="mt-4 text-sm leading-7 text-slate-300">Tetap berada di halaman ujian dan gunakan layar penuh jika tersedia. Kejadian pertama mendapat peringatan; kejadian kedua mengunci ujian untuk diperiksa pengawas. Waktu tetap berjalan.</p>
                    <p class="mt-3 text-xs leading-6 text-slate-400">Matikan notifikasi sebelum mulai. Gangguan teknis dapat diperiksa pengawas. Sinyal browser bukan bukti pasti kecurangan.</p>
                    <button id="security-continue" type="button" class="mt-6 w-full rounded-xl bg-cyan-400 px-5 py-3 font-semibold text-slate-950">Saya paham, masuk mode ujian</button>
                    <p id="security-network" aria-live="polite" class="mt-3 text-sm text-amber-300"></p>
                </section>
            </div>
        </div>
    @endif
    <div class="sticky top-3 z-20 mb-6 flex items-center justify-between gap-4 rounded-2xl border border-cyan-400/30 bg-slate-950/95 px-5 py-4 shadow-xl backdrop-blur">
        <div>
            <p class="text-xs text-slate-500">Sisa waktu</p>
            <p id="exam-timer" class="mt-1 font-mono text-xl font-semibold text-cyan-300">--:--:--</p>
        </div>
        <div class="text-right">
            <p id="save-status" class="text-xs text-slate-400">Jawaban tersimpan otomatis</p>
            <button id="retry-save" type="button" hidden class="mt-2 text-xs font-semibold text-amber-300">Coba simpan lagi</button>
            <p class="mt-1 text-xs text-amber-300">Jangan menutup halaman ujian</p>
            @if ($attempt->security_enabled)<p id="security-count" class="mt-1 text-xs text-rose-300">Pelanggaran {{ $attempt->violation_count }}/2</p>@endif
        </div>
    </div>

    <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">{{ $attempt->assignment->assessmentSubject->assessmentPeriod->name }}</p>
        <h2 class="mt-2 text-2xl font-semibold text-white">{{ $attempt->assignment->assessmentSubject->subject->name }}</h2>
        <p class="mt-2 text-sm text-slate-400">{{ $questions->count() }} soal · Dimulai {{ $attempt->started_at->format('H:i:s') }}</p>
    </section>

    <fieldset id="exam-content" @disabled($attempt->security_enabled) @if($attempt->security_enabled) hidden @endif>
    <div class="mt-6 space-y-4">
        @foreach ($questions as $question)
            <article class="rounded-2xl border border-white/10 bg-white/[0.035] p-5">
                <p class="text-xs font-semibold text-cyan-300">Soal {{ $loop->iteration }} dari {{ $questions->count() }}</p>
                <p class="mt-3 whitespace-pre-line text-sm leading-7 text-white">{{ $question->question_text }}</p>
                <div class="mt-4 grid gap-2">
                    @foreach ($question->options as $key => $option)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 px-4 py-3 text-sm text-slate-300 transition hover:border-cyan-400/30 hover:bg-cyan-400/5">
                            <input type="radio" name="question_{{ $question->id }}" value="{{ $key }}"
                                data-save-url="{{ route('student.exams.answer', [$attempt, $question]) }}"
                                @checked($answers->get($question->id)?->answer === $key)
                                class="mt-1">
                            <span><strong class="text-cyan-300">{{ $key }}.</strong> {{ $option }}</span>
                        </label>
                    @endforeach
                </div>
            </article>
        @endforeach
    </div>

    <form id="submit-exam" method="POST" action="{{ route('student.exams.submit', $attempt) }}" class="mt-6">
        @csrf
        <button class="w-full rounded-xl bg-emerald-400 px-5 py-4 font-semibold text-slate-950">Selesai dan kumpulkan jawaban</button>
    </form>
    </fieldset>

    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        // Base the countdown on server time, not the student's system clock.
        const remainingAtLoad = {{ max(0, $deadline->getTimestamp() - now()->getTimestamp()) * 1000 }};
        const loadedAt = performance.now();
        const timer = document.getElementById('exam-timer');
        const status = document.getElementById('save-status');
        const submitForm = document.getElementById('submit-exam');
        const retryButton = document.getElementById('retry-save');
        const inputs = document.querySelectorAll('input[data-save-url]');
        const pending = new Map();
        let saving = null;
        let submitting = false;
        window.examAutoSubmit = false;

        const flushAnswers = () => {
            if (saving) return saving;
            saving = (async () => {
                while (pending.size && !window.examAutoSubmit && !window.examSecurity?.isBlocked()) {
                    const [url, answer] = pending.entries().next().value;
                    status.textContent = `Menyimpan jawaban (${pending.size} tertunda)...`;
                    const controller = new AbortController();
                    const timeout = setTimeout(() => controller.abort(), 15000);
                    try {
                        const response = await fetch(url, {
                            method: 'PUT',
                            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                            body: JSON.stringify({answer}),
                            signal: controller.signal,
                        });
                        const result = await response.json().catch(() => ({}));
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

        inputs.forEach((input) => input.addEventListener('change', () => {
            if (window.examSecurity?.isBlocked()) return;
            pending.set(input.dataset.saveUrl, input.value);
            flushAnswers();
        }));
        retryButton.addEventListener('click', flushAnswers);
        window.addEventListener('online', flushAnswers);
        window.addEventListener('examsecurity:ready', flushAnswers);
        window.addEventListener('beforeunload', (event) => {
            if (pending.size && !window.examAutoSubmit) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        submitForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (submitting || window.examSecurity?.isBlocked()) return;
            // Native confirmation may exit fullscreen on some devices. Do not count it.
            window.examSecurity?.pause();
            if (!confirm('Yakin ingin mengumpulkan seluruh jawaban?')) {
                window.examSecurity?.returnToExam();
                return;
            }
            submitting = true;
            inputs.forEach((input) => { input.disabled = true; });
            submitForm.querySelector('button').disabled = true;
            await flushAnswers();
            if (window.examAutoSubmit) return;
            if (pending.size) {
                submitting = false;
                inputs.forEach((input) => { input.disabled = false; });
                submitForm.querySelector('button').disabled = false;
                window.examSecurity?.returnToExam();
                return;
            }
            window.examAutoSubmit = true;
            submitForm.submit();
        });

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
        <script src="{{ asset('js/exam-security.js') }}?v=20260904-1" defer></script>
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
