@extends('layouts.app')

@section('title', 'Hasil Ujian')
@section('eyebrow', 'Laporan Akademik')
@section('heading', 'Cetak Nilai Ujian')

@section('content')
    <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6 md:p-8">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">AAS · AAT · UUB · Periode Lainnya</p>
        <h2 class="mt-2 text-2xl font-semibold text-white">Pilih periode selesai dan kelas</h2>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-400">Rekap mengambil nilai akhir hasil pengerjaan ujian non-ATS. Periode harus berstatus Selesai sebelum dapat dicetak.</p>

        <div class="mt-8 space-y-4">
            @forelse ($periods as $period)
                <article class="rounded-2xl border border-white/10 bg-slate-950/40 p-5">
                    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full bg-emerald-400/10 px-2.5 py-1 text-xs text-emerald-200">{{ strtoupper($period->type->value) }}</span>
                                <span class="text-xs text-slate-500">{{ $period->status->value === 'closed' ? 'Selesai' : $period->status->value }}</span>
                            </div>
                            <h3 class="mt-2 font-semibold text-white">{{ $period->name }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ $period->academicYear->name }} · {{ $period->starts_on->format('d/m/Y') }}–{{ $period->ends_on->format('d/m/Y') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($period->accessibleClasses as $class)
                                <a href="{{ route('reports.exam-results.print', [$period, $class]) }}" target="_blank"
                                    class="rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-2 text-xs font-medium text-emerald-200 hover:bg-emerald-400/20">
                                    Cetak {{ $class->name }}
                                </a>
                            @empty
                                <span class="text-xs text-slate-500">Tidak ada kelas yang dapat dicetak oleh akun ini.</span>
                            @endforelse
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-white/10 p-10 text-center text-sm text-slate-500">Belum ada periode non-ATS yang berstatus Selesai.</div>
            @endforelse
        </div>

        @if ($periods->hasPages())
            <div class="mt-6">{{ $periods->links() }}</div>
        @endif
    </section>
@endsection
