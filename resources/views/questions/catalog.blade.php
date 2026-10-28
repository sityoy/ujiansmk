@extends('layouts.app')

@section('title', 'Bank Soal')
@section('eyebrow', 'Guru & Asesmen')
@section('heading', 'Bank Soal')

@section('content')
    <section class="rounded-3xl border border-violet-400/20 bg-violet-400/[0.045] p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">Ruang Kerja Guru</p>
        <h2 class="mt-2 text-2xl font-semibold text-white">Soal sesuai mapel dan kelas</h2>
        <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-400">
            Guru hanya dapat membuka komponen yang ditugaskan kepadanya oleh Panitia/Super Admin.
            Bank soal tersedia untuk ATS, AAS/AAT, UUB, dan asesmen lainnya. ATS tetap khusus isian singkat dan esai.
        </p>
    </section>

    <form method="GET" action="{{ route('question-bank.index') }}" class="mt-6 grid gap-3 rounded-2xl border border-white/10 bg-white/[0.025] p-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_auto_auto]">
        <select name="period_id" class="rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm">
            <option value="">Semua periode</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}" @selected($periodId === $period->id)>{{ $period->name }} · {{ $period->academicYear->name }}</option>
            @endforeach
        </select>
        <select name="class_id" class="rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm">
            <option value="">Semua kelas</option>
            @foreach ($classes as $class)
                <option value="{{ $class->id }}" @selected($classId === $class->id)>{{ $class->name }} · {{ $class->academicYear->name }}</option>
            @endforeach
        </select>
        <input name="q" value="{{ $search }}" placeholder="Cari mapel atau kode"
            class="rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm outline-none focus:border-violet-400">
        <button class="rounded-xl bg-violet-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Tampilkan</button>
        <a href="{{ route('question-bank.index') }}" class="rounded-xl border border-white/10 px-4 py-2.5 text-center text-sm text-slate-300">Reset</a>
    </form>

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($components as $component)
            <article class="flex flex-col rounded-3xl border border-white/10 bg-white/[0.035] p-5">
                <div class="flex items-start justify-between gap-3">
                    <span class="rounded-full bg-violet-400/10 px-3 py-1 text-xs font-medium text-violet-200">{{ strtoupper($component->assessmentPeriod->type->value) }}</span>
                    <span class="text-xs text-slate-500">{{ $component->questions_count }} soal</span>
                </div>
                <h3 class="mt-4 text-lg font-semibold text-white">{{ $component->subject->name }}</h3>
                <p class="mt-1 text-sm text-slate-400">{{ $component->schoolClass->name }} · {{ $component->assessmentPeriod->name }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $component->assessmentPeriod->academicYear->name }} · Total bobot {{ number_format((float) ($component->questions_sum_points ?? 0), 2, ',', '.') }}</p>
                @if (auth()->user()->role->value !== 'teacher')
                    <p class="mt-3 text-xs text-slate-500">Guru: {{ $component->teacher?->name ?? 'belum ditetapkan' }}</p>
                @endif
                <a href="{{ route('scheduling.questions.index', $component) }}" class="mt-5 inline-flex justify-center rounded-xl bg-violet-400 px-4 py-2.5 text-sm font-semibold text-slate-950 xl:mt-auto">Kelola soal</a>
            </article>
        @empty
            <div class="rounded-3xl border border-dashed border-white/10 p-12 text-center text-sm leading-6 text-slate-500 md:col-span-2 xl:col-span-3">
                Belum ada mapel dan kelas yang ditugaskan untuk akun ini. Panitia atau Super Admin perlu memilih guru pengoreksi pada Penjadwalan terlebih dahulu.
            </div>
        @endforelse
    </div>

    @if ($components->hasPages())
        <div class="mt-5 rounded-2xl border border-white/10 bg-white/[0.025] p-4">{{ $components->links() }}</div>
    @endif
@endsection
