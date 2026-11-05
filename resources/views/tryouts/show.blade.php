@extends('layouts.tryout')

@section('title', $trialExam->title)

@section('content')
    <form id="trial-form" method="POST" action="{{ route('tryout.submit', [$trialExam, $attempt]) }}">
        @csrf
        <div class="sticky top-0 z-10 mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-slate-950/95 p-4 shadow-xl">
            <div><p class="font-semibold">{{ $trialExam->title }}</p><p class="text-xs text-slate-400">{{ $attempt->participant_name }} · {{ $questions->count() }} soal</p></div>
            <div class="flex items-center gap-3"><span id="timer" class="rounded-xl bg-amber-400/10 px-3 py-2 font-mono text-amber-200">--:--</span><button class="rounded-xl bg-cyan-400 px-4 py-2 text-sm font-semibold text-slate-950" onclick="return confirm('Kumpulkan jawaban sekarang?')">Kumpulkan</button></div>
        </div>

        <div class="space-y-5">
            @foreach ($questions as $question)
                <article class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
                    <div class="flex gap-4"><span class="grid size-9 shrink-0 place-items-center rounded-xl bg-cyan-400/10 font-semibold text-cyan-300">{{ $loop->iteration }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="whitespace-pre-line leading-7 text-white">{{ $question->question_text }}</div>
                            @if ($question->question_image_path)<img src="{{ asset('storage/'.$question->question_image_path) }}" alt="Gambar soal" class="mt-4 max-h-80 rounded-2xl border border-white/10 object-contain">@endif
                            <div class="mt-5 grid gap-3">
                                @foreach (($question->options ?? []) as $key => $option)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-slate-950/50 px-4 py-3 hover:border-cyan-400/30">
                                        <input type="radio" name="answers[{{ $question->id }}]" value="{{ $key }}" class="mt-1">
                                        <span><strong class="mr-2 text-cyan-300">{{ $key }}.</strong>{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <button class="mt-6 w-full rounded-xl bg-cyan-400 px-4 py-4 font-semibold text-slate-950" onclick="return confirm('Yakin ingin mengumpulkan semua jawaban?')">Kumpulkan jawaban</button>
    </form>

    <script>
        (() => {
            let remaining = {{ $remainingSeconds }};
            const timer = document.getElementById('timer');
            const form = document.getElementById('trial-form');
            let submitted = false;
            const render = () => {
                const minutes = Math.floor(remaining / 60).toString().padStart(2, '0');
                const seconds = (remaining % 60).toString().padStart(2, '0');
                timer.textContent = minutes + ':' + seconds;
                if (remaining <= 0 && !submitted) {
                    submitted = true;
                    form.submit();
                    return;
                }
                remaining--;
            };
            render();
            setInterval(render, 1000);
            form.addEventListener('submit', () => submitted = true);
        })();
    </script>
@endsection
