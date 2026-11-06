@extends('layouts.app')

@section('title', 'Hasil Ujian')
@section('eyebrow', 'Laporan Akademik')
@section('heading', 'Cetak Nilai Ujian')

@section('content')
    @php
        $typeLabels = ['ats' => 'ATS', 'aas' => 'AAS', 'aat' => 'AAT', 'uub' => 'UUB', 'other' => 'Lainnya'];
        $statusLabels = ['draft' => 'Draf', 'published' => 'Terbit', 'active' => 'Aktif', 'closed' => 'Selesai'];
    @endphp

    <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6 md:p-8">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">ATS · AAS · AAT · UUB · Lainnya</p>
        <h2 class="mt-2 text-2xl font-semibold text-white">Hasil seluruh periode ujian</h2>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-400">Pilih tahun pelajaran, jenis asesmen, dan status periode. Rekap mengambil nilai akhir yang sudah tersimpan pada pengerjaan peserta.</p>

        <form method="GET" class="mt-6 grid gap-3 rounded-2xl border border-white/10 bg-slate-950/40 p-4 md:grid-cols-4">
            <select name="academic_year_id" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
                <option value="">Semua tahun pelajaran</option>
                @foreach ($academicYears as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? '') == $year->id)>{{ $year->name }}{{ $year->is_active ? ' · Aktif' : '' }}</option>@endforeach
            </select>
            <select name="type" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
                <option value="">Semua jenis asesmen</option>
                @foreach ($assessmentTypes as $type)<option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $typeLabels[$type->value] }}</option>@endforeach
            </select>
            <select name="status" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
                <option value="">Semua status periode</option>
                @foreach ($periodStatuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $statusLabels[$status->value] }}</option>@endforeach
            </select>
            <div class="flex items-center gap-3"><button class="rounded-xl bg-emerald-400 px-4 py-3 text-sm font-semibold text-slate-950">Tampilkan</button><a href="{{ route('reports.exam-results.index') }}" class="text-sm text-slate-400">Reset</a></div>
        </form>

        <div class="mt-8 space-y-4">
            @forelse ($periods as $period)
                <article class="rounded-2xl border border-white/10 bg-slate-950/40 p-5">
                    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full bg-emerald-400/10 px-2.5 py-1 text-xs text-emerald-200">{{ $typeLabels[$period->type->value] }}</span>
                                <span class="rounded-full bg-white/5 px-2.5 py-1 text-xs text-slate-400">{{ $statusLabels[$period->status->value] }}</span>
                            </div>
                            <h3 class="mt-2 font-semibold text-white">{{ $period->name }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ $period->academicYear->name }}{{ $period->academicYear->is_active ? ' · Tahun aktif' : '' }} · {{ $period->starts_on->format('d/m/Y') }}–{{ $period->ends_on->format('d/m/Y') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($period->accessibleClasses as $class)
                                <a href="{{ route('reports.exam-results.print', [$period, $class]) }}" target="_blank" class="rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-2 text-xs font-medium text-emerald-200 hover:bg-emerald-400/20">Lihat/Cetak {{ $class->name }}</a>
                            @empty
                                <span class="text-xs text-slate-500">Tidak ada kelas yang dapat diakses oleh akun ini.</span>
                            @endforelse
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-white/10 p-10 text-center text-sm text-slate-500">Tidak ada periode sesuai filter.</div>
            @endforelse
        </div>
        @if ($periods->hasPages())<div class="mt-6">{{ $periods->links() }}</div>@endif
    </section>
@endsection
