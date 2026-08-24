@extends('layouts.app')

@section('title', 'Rapor ATS '.$schoolClass->name)
@section('eyebrow', 'Laporan Akademik')
@section('heading', 'Rapor ATS · '.$schoolClass->name)

@section('content')
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <a href="{{ route('reports.midterm.index') }}" class="text-xs font-medium text-cyan-300 hover:text-cyan-200">← Kembali ke periode ATS</a>
            <h2 class="mt-3 text-2xl font-semibold text-white">{{ $period->name }}</h2>
            <p class="mt-2 text-sm text-slate-400">{{ $schoolClass->name }} · {{ $subjects->count() }} mata pelajaran</p>
        </div>
        <span class="w-fit rounded-full px-3 py-1 text-xs font-medium {{ $is_complete ? 'bg-emerald-400/10 text-emerald-200' : 'bg-amber-400/10 text-amber-200' }}">
            {{ $is_complete ? 'Nilai lengkap' : 'Peringkat sementara — nilai belum lengkap' }}
        </span>
    </div>

    <section class="mt-6 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-white/10 bg-slate-950/60 text-xs uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="sticky left-0 bg-slate-950 px-4 py-4">Siswa</th>
                        @foreach ($subjects as $assessmentSubject)
                            <th class="whitespace-nowrap px-4 py-4 text-center">{{ $assessmentSubject->subject->name }}</th>
                        @endforeach
                        <th class="px-4 py-4 text-center">Rata-rata</th>
                        <th class="px-4 py-4 text-center">Rank</th>
                        <th class="px-4 py-4 text-right">Rapor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($rows as $row)
                        <tr class="text-slate-300">
                            <td class="sticky left-0 min-w-56 bg-slate-950/95 px-4 py-4">
                                <p class="font-medium text-white">{{ $row['student']->full_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['student']->student_number }}</p>
                            </td>
                            @foreach ($subjects as $assessmentSubject)
                                <td class="px-4 py-4 text-center">
                                    {{ $row['scores'][$assessmentSubject->id] === null ? '—' : number_format($row['scores'][$assessmentSubject->id], 2, ',', '.') }}
                                </td>
                            @endforeach
                            <td class="px-4 py-4 text-center font-semibold text-cyan-200">{{ number_format($row['average'], 2, ',', '.') }}</td>
                            <td class="px-4 py-4 text-center font-semibold text-amber-200">{{ $row['rank'] ?? '—' }}</td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('reports.midterm.print', [$period, $schoolClass, $row['student']]) }}" target="_blank"
                                    class="rounded-xl border border-white/10 px-3 py-2 text-xs font-medium text-slate-200 hover:border-cyan-400/30 hover:bg-cyan-400/10">
                                    Cetak
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $subjects->count() + 4 }}" class="px-6 py-12 text-center text-slate-500">Belum ada siswa aktif di kelas ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
