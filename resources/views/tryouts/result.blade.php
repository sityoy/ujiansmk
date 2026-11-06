@extends('layouts.tryout')

@section('title', 'Ujian Selesai')

@section('content')
    <div class="mx-auto max-w-xl text-center">
        <section class="rounded-3xl border border-emerald-400/20 bg-emerald-400/[0.04] p-8">
            <div class="mx-auto grid size-20 place-items-center rounded-full bg-emerald-400/10 text-4xl text-emerald-300">✓</div>
            <p class="mt-6 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300">Jawaban berhasil dikumpulkan</p>
            <h1 class="mt-3 text-2xl font-semibold">{{ $trialAttempt->participant_name }}</h1>
            <p class="mt-2 text-slate-400">{{ $trialExam->title }}</p>
            <div class="mt-7 rounded-2xl border border-white/10 bg-slate-950/60 p-5">
                <p class="font-medium text-white">Ujian uji coba telah selesai.</p>
                <p class="mt-2 text-sm leading-6 text-slate-400">Nilai dan jumlah jawaban benar tidak ditampilkan kepada peserta. Panitia hanya melihat status pengerjaan dan catatan pengawasan.</p>
            </div>
        </section>
    </div>
@endsection
