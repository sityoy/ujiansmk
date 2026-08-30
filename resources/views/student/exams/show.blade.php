@extends('layouts.app')

@section('title', 'Mengerjakan Ujian')
@section('eyebrow', 'Ujian Berlangsung')
@section('heading', $attempt->assignment->assessmentSubject->subject->name)

@section('content')
    <div class="sticky top-3 z-20 mb-6 flex items-center justify-between gap-4 rounded-2xl border border-cyan-400/30 bg-slate-950/95 px-5 py-4 shadow-xl backdrop-blur">
        <div>
            <p class="text-xs text-slate-500">Sisa waktu</p>
            <p id="exam-timer" class="mt-1 font-mono text-xl font-semibold text-cyan-300">--:--:--</p>
        </div>
        <div class="text-right">
            <p id="save-status" class="text-xs text-slate-400">Jawaban tersimpan otomatis</p>
            <p class="mt-1 text-xs text-amber-300">Jangan menutup halaman ujian</p>
        </div>
    </div>

    <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">{{ $attempt->assignment->assessmentSubject->assessmentPeriod->name }}</p>
        <h2 class="mt-2 text-2xl font-semibold text-white">{{ $attempt->assignment->assessmentSubject->subject->name }}</h2>
        <p class="mt-2 text-sm text-slate-400">{{ $questions->count() }} soal · Dimulai {{ $attempt->started_at->format('H:i:s') }}</p>
    </section>

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

    <form id="submit-exam" method="POST" action="{{ route('student.exams.submit', $attempt) }}" class="mt-6" onsubmit="return window.examAutoSubmit || confirm('Yakin ingin mengumpulkan seluruh jawaban?')">
        @csrf
        <button class="w-full rounded-xl bg-emerald-400 px-5 py-4 font-semibold text-slate-950">Selesai dan kumpulkan jawaban</button>
    </form>

    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const deadline = {{ $deadline->getTimestamp() * 1000 }};
        const timer = document.getElementById('exam-timer');
        const status = document.getElementById('save-status');
        const submitForm = document.getElementById('submit-exam');
        window.examAutoSubmit = false;

        const updateTimer = () => {
            const remaining = Math.max(0, deadline - Date.now());
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

        document.querySelectorAll('input[data-save-url]').forEach((input) => {
            input.addEventListener('change', async () => {
                status.textContent = 'Menyimpan jawaban...';
                try {
                    const response = await fetch(input.dataset.saveUrl, {
                        method: 'PUT',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                        body: JSON.stringify({answer: input.value}),
                    });
                    if (!response.ok) throw new Error('Jawaban gagal disimpan');
                    const result = await response.json();
                    status.textContent = `Tersimpan pukul ${result.saved_at}`;
                } catch (error) {
                    status.textContent = 'Gagal menyimpan. Periksa koneksi lalu pilih kembali jawaban.';
                    status.classList.add('text-rose-300');
                }
            });
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden && !window.examAutoSubmit) {
                fetch(@json(route('student.exams.incident', $attempt)), {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                    body: JSON.stringify({category: 'tab_hidden'}),
                    keepalive: true,
                });
            }
        });
    </script>
@endsection
