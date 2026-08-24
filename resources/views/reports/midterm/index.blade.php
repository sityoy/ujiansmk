@extends('layouts.app')

@section('title', 'Rapor ATS')
@section('eyebrow', 'Laporan Akademik')
@section('heading', 'Rapor Tengah Semester')

@section('content')
    <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6 md:p-8">
        <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Khusus ATS</p>
            <h2 class="mt-2 text-2xl font-semibold text-white">Pilih periode dan kelas</h2>
            <p class="mt-3 text-sm leading-6 text-slate-400">
                Rapor menampilkan nilai setiap mata pelajaran dan peringkat siswa di kelas. Periode selain ATS tidak ditampilkan.
            </p>
        </div>

        <div class="mt-8 space-y-4">
            @forelse ($periods as $period)
                <article class="rounded-2xl border border-white/10 bg-slate-950/40 p-5">
                    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <h3 class="font-semibold text-white">{{ $period->name }}</h3>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $period->academicYear->name }} · {{ $period->starts_on->translatedFormat('d M Y') }}–{{ $period->ends_on->translatedFormat('d M Y') }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($period->assessmentSubjects->pluck('schoolClass')->filter()->unique('id')->sortBy('name') as $class)
                                <a href="{{ route('reports.midterm.show', [$period, $class]) }}"
                                    class="rounded-xl border border-cyan-400/20 bg-cyan-400/10 px-4 py-2 text-xs font-medium text-cyan-200 transition hover:bg-cyan-400/20">
                                    {{ $class->name }}
                                </a>
                            @empty
                                <span class="text-xs text-slate-500">Belum ada kelas pada periode ini.</span>
                            @endforelse
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-white/10 p-10 text-center">
                    <p class="text-sm font-medium text-slate-300">Belum ada periode ATS.</p>
                    <p class="mt-2 text-xs text-slate-500">Buat periode Asesmen Tengah Semester terlebih dahulu.</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
