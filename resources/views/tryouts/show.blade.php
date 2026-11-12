@extends('layouts.tryout')

@section('title', $trialExam->title)

@section('content')
    <div id="exam-security"
        data-state-url="{{ route('tryout.security', [$trialExam, $attempt]) }}"
        data-event-url="{{ route('tryout.incident', [$trialExam, $attempt]) }}"
        data-index-url="{{ route('tryout.result', [$trialExam, $attempt]) }}"
        data-attempt-id="trial-{{ $attempt->id }}"
        data-count="{{ $attempt->violation_count }}"
        data-locked="{{ $attempt->security_locked_at ? '1' : '0' }}">
        <div id="security-panel" role="dialog" aria-modal="true" aria-labelledby="security-title" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/95 p-6 backdrop-blur">
            <section class="mx-auto mt-12 max-w-xl rounded-3xl border border-amber-400/30 bg-slate-900 p-6 shadow-xl">
                <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">Pengawasan uji coba · batas 2 pelanggaran</p>
                <h2 id="security-title" class="mt-4 text-2xl font-semibold">Siap masuk mode ujian?</h2>
                <p id="security-message" class="mt-4 text-sm leading-7 text-slate-300">Pengamanan sama dengan Ujian Inti: layar penuh wajib dan sistem memantau perpindahan tab/aplikasi, bubble, bilah notifikasi, split screen, tombol terlarang, serta navigasi keluar. Pelanggaran kedua mengunci ujian.</p>
                <p class="mt-3 text-xs leading-6 text-slate-400">Aktifkan Jangan Ganggu, tutup aplikasi lain, dan jangan menarik panel notifikasi. Waktu tetap berjalan ketika ujian terkunci.</p>
                <p class="mt-3 rounded-xl border border-cyan-400/20 bg-cyan-400/[0.06] p-3 text-sm text-cyan-100">Setiap jawaban PG benar bernilai tetap <strong>2 poin</strong>. Nilai PG maksimal 100.</p>
                <button id="security-continue" type="button" class="mt-6 w-full rounded-xl bg-cyan-400 px-5 py-3 font-semibold text-slate-950">Saya paham, masuk mode ujian</button>
                <p id="security-network" aria-live="polite" class="mt-3 text-sm text-amber-300"></p>
            </section>
        </div>
    </div>

    <form id="trial-form" method="POST" action="{{ route('tryout.submit', [$trialExam, $attempt]) }}">
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
            </div>
            <div class="flex items-center justify-end">
                <span id="security-count" class="rounded-xl bg-rose-400/10 px-3 py-2 text-xs text-rose-200">Pelanggaran {{ $attempt->violation_count }}/2</span>
            </div>
        </div>

        <fieldset id="exam-content" disabled hidden>
            <div class="space-y-5">
                @foreach ($questions as $question)
                    <article class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
                        <div class="flex gap-4"><span class="grid size-9 shrink-0 place-items-center rounded-xl bg-cyan-400/10 font-semibold text-cyan-300">{{ $loop->iteration }}</span>
                            <div class="min-w-0 flex-1">
                                <x-question-prompt :question="$question" />
                                <div class="mt-5 grid gap-3">
                                    @foreach (collect($question->options)->sortKeys() as $key => $option)
                                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-slate-950/50 px-4 py-3 hover:border-cyan-400/30">
                                            <input type="radio" name="answers[{{ $question->id }}]" value="{{ $key }}"
                                                data-save-url="{{ route('tryout.answer', [$trialExam, $attempt, $question]) }}"
                                                @checked($answers->get($question->id)?->answer === $key) class="mt-1">
                                            <div class="min-w-0 flex-1"><div class="flex items-start gap-2"><strong class="mr-2 text-cyan-300">{{ $key }}.</strong><x-question-text :text="$option" :format="$question->text_format" /></div>
                                                @if($question->option_file_paths[$key] ?? null)
                                                    @php($optionIsImage = in_array(strtolower(pathinfo($question->option_file_paths[$key], PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp'], true))
                                                    @if($optionIsImage)<img src="{{ Storage::url($question->option_file_paths[$key]) }}" alt="Lampiran pilihan {{ $key }}" class="mt-3 max-h-64 max-w-full rounded-xl border border-white/10 bg-white object-contain p-2">@else<a href="{{ Storage::url($question->option_file_paths[$key]) }}" target="_blank" class="mt-2 block text-xs font-semibold text-cyan-300">Buka lampiran pilihan {{ $key }}</a>@endif
                                                @endif
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <button class="mt-6 w-full rounded-xl bg-cyan-400 px-4 py-4 font-semibold text-slate-950">Kumpulkan jawaban</button>
        </fieldset>
    </form>

    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const remainingAtLoad = {{ max(0, $deadline->getTimestamp() - now()->getTimestamp()) * 1000 }};
        const loadedAt = performance.now();
        const form = document.getElementById('trial-form');
        const timer = document.getElementById('timer');
        const saveStatus = document.getElementById('save-status');
        const inputs = document.querySelectorAll('[data-save-url]');
        const pending = new Map();
        let saving = null;
        let submitting = false;
        window.examAutoSubmit = false;

        const flushAnswers = () => {
            if (saving) return saving;
            saving = (async () => {
                while (pending.size && !window.examAutoSubmit && !window.examSecurity?.isBlocked()) {
                    const [url, answer] = pending.entries().next().value;
                    saveStatus.textContent = 'Menyimpan jawaban...';
                    try {
                        const response = await fetch(url, {
                            method: 'PUT',
                            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                            body: JSON.stringify({answer}),
                        });
                        const result = await response.json().catch(() => ({}));
                        if (result.errors?.security) window.examSecurity?.check();
                        if (!response.ok) throw new Error(result.message || 'Jawaban gagal disimpan.');
                        if (pending.get(url) === answer) pending.delete(url);
                        saveStatus.textContent = 'Tersimpan pukul ' + result.saved_at;
                        saveStatus.classList.remove('text-rose-300');
                    } catch (error) {
                        saveStatus.textContent = error.message + ' Jawaban akan dicoba lagi.';
                        saveStatus.classList.add('text-rose-300');
                        break;
                    }
                }
            })().finally(() => { saving = null; });
            return saving;
        };

        inputs.forEach(input => input.addEventListener('change', () => {
            if (window.examSecurity?.isBlocked()) return;
            pending.set(input.dataset.saveUrl, input.value);
            flushAnswers();
        }));
        window.addEventListener('online', flushAnswers);
        window.addEventListener('examsecurity:ready', flushAnswers);

        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (submitting || window.examSecurity?.isBlocked()) return;
            window.examSecurity?.pause();
            if (!confirm('Yakin ingin mengumpulkan seluruh jawaban? Nilai tidak akan ditampilkan.')) {
                window.examSecurity?.returnToExam();
                return;
            }
            submitting = true;
            await flushAnswers();
            if (pending.size) {
                submitting = false;
                window.examSecurity?.returnToExam();
                return;
            }
            window.examAutoSubmit = true;
            form.submit();
        });

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
    <script src="{{ asset('js/exam-security.js') }}?v=20261108-2" defer></script>
@endsection
