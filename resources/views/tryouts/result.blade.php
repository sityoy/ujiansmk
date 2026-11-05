@extends('layouts.tryout')

@section('title', 'Hasil '.$trialExam->title)

@section('content')
    <div class="mx-auto max-w-xl text-center">
        <section class="rounded-3xl border border-emerald-400/20 bg-emerald-400/[0.04] p-8">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300">Ujian selesai</p>
            <h1 class="mt-3 text-2xl font-semibold">{{ $trialAttempt->participant_name }}</h1>
            <p class="mt-2 text-slate-400">{{ $trialExam->title }}</p>
            <div class="mx-auto mt-7 grid size-40 place-items-center rounded-full border-8 border-emerald-400/20 bg-slate-950">
                <div><p class="text-xs uppercase tracking-wider text-slate-500">Nilai</p><p class="text-4xl font-bold text-emerald-300">{{ number_format((float) $trialAttempt->score, 2, ',', '.') }}</p></div>
            </div>
            <p class="mt-6 text-sm text-slate-300">{{ $trialAttempt->correct_answers }} jawaban benar dari {{ $trialAttempt->total_questions }} soal.</p>
            <p class="mt-2 text-xs text-slate-500">Hasil uji coba tidak masuk ke nilai ujian resmi.</p>
        </section>
    </div>
@endsection
