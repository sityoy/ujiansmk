@extends('layouts.app')

@section('title', 'Rapor ATS '.$schoolClass->name)
@section('eyebrow', 'Laporan Akademik')
@section('heading', 'Rapor ATS · '.$schoolClass->name)

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <a href="{{ route('reports.midterm.index') }}" class="text-xs font-medium text-cyan-300 hover:text-cyan-200">← Kembali ke periode ATS</a>
            <h2 class="mt-3 text-2xl font-semibold text-white">{{ $period->name }}</h2>
            <p class="mt-2 text-sm text-slate-400">{{ $schoolClass->name }} · {{ $subjects->count() }} mata pelajaran</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($canEdit)
                <a href="{{ route('reports.midterm.edit', [$period, $schoolClass]) }}" class="rounded-xl bg-cyan-400 px-4 py-2 text-xs font-semibold text-slate-950">Kelola data rapor</a>
            @endif
            <span class="w-fit rounded-full px-3 py-1 text-xs font-medium {{ $is_complete ? 'bg-emerald-400/10 text-emerald-200' : 'bg-amber-400/10 text-amber-200' }}">
                {{ $is_complete ? 'Nilai lengkap' : 'Peringkat sementara — nilai belum lengkap' }}
            </span>
        </div>
    </div>

    <section class="mt-6 rounded-3xl border border-sky-400/20 bg-sky-400/[0.035] p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-300">Cetak Nilai Rapor Siswa</p>
        <h2 class="mt-2 text-xl font-semibold text-white">Pengaturan Hasil Cetak</h2>

        @if ($canConfigure)
            <form method="POST" action="{{ route('reports.midterm.settings.update', [$period, $schoolClass]) }}" class="mt-5 space-y-5">
                @csrf @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <label class="text-sm font-medium text-slate-300">Ukuran Kertas
                        <select name="report_paper_size" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 font-normal">
                            <option value="a4" @selected(old('report_paper_size', $period->report_paper_size ?? 'f4') === 'a4')>A4 (210 × 297 mm)</option>
                            <option value="f4" @selected(old('report_paper_size', $period->report_paper_size ?? 'f4') === 'f4')>F4 / Folio (215,9 × 330,2 mm)</option>
                        </select>
                    </label>
                    @foreach ([
                        'report_margin_left_mm' => ['Margin Kiri (mm)', $period->report_margin_left_mm ?? 8],
                        'report_margin_right_mm' => ['Margin Kanan (mm)', $period->report_margin_right_mm ?? 8],
                        'report_margin_top_mm' => ['Margin Atas (mm)', $period->report_margin_top_mm ?? 8],
                        'report_margin_bottom_mm' => ['Margin Bawah (mm)', $period->report_margin_bottom_mm ?? 8],
                    ] as $field => [$label, $default])
                        <label class="text-sm font-medium text-slate-300">{{ $label }}
                            <input type="number" name="{{ $field }}" value="{{ old($field, $default) }}" min="5" max="25" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 font-normal">
                        </label>
                    @endforeach
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <label class="text-sm font-medium text-slate-300">Tempat Penerbitan
                        <input name="report_place" value="{{ old('report_place', $period->report_place) }}" required maxlength="120" placeholder="Contoh: Jakarta" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 font-normal">
                    </label>
                    <label class="text-sm font-medium text-slate-300">Tanggal Rapor
                        <input type="date" name="report_date" value="{{ old('report_date', $period->report_date?->format('Y-m-d') ?? $period->ends_on?->format('Y-m-d')) }}" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 font-normal">
                    </label>
                    <label class="text-sm font-medium text-slate-300">Skala Isi
                        <div class="mt-2 flex items-center gap-2"><input type="number" name="report_scale_percent" value="{{ old('report_scale_percent', $period->report_scale_percent ?? 90) }}" min="70" max="100" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 font-normal"><span class="text-slate-500">%</span></div>
                    </label>
                    <label class="text-sm font-medium text-slate-300">Posisi Tanda Tangan KS
                        <input value="Di bawah Orang Tua/Wali dan Wali Kelas" disabled class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 font-normal text-slate-500">
                    </label>
                    <label class="text-sm font-medium text-slate-300">Pilih Kelas
                        <select data-report-class-picker class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 font-normal">
                            @foreach ($printClasses as $class)
                                <option value="{{ route('reports.midterm.show', [$period, $class]) }}" @selected($class->is($schoolClass))>{{ $class->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4">
                    <p class="text-xs leading-5 text-slate-500">Setiap rapor dimulai dari halaman 1. Nama wali kelas terisi otomatis. Rekomendasi satu lembar: F4, margin 8 mm, skala 90%.</p>
                    <button class="rounded-xl bg-sky-400 px-5 py-3 text-sm font-semibold text-slate-950">Simpan Pengaturan</button>
                </div>
            </form>
        @else
            <div class="mt-4 grid gap-3 text-sm text-slate-400 md:grid-cols-2 xl:grid-cols-4">
                <p><span class="block text-xs text-slate-600">Ukuran Kertas</span>{{ strtoupper($period->report_paper_size ?? 'f4') }}</p>
                <p><span class="block text-xs text-slate-600">Margin Kiri/Kanan/Atas/Bawah</span>{{ $period->report_margin_left_mm ?? 8 }}/{{ $period->report_margin_right_mm ?? 8 }}/{{ $period->report_margin_top_mm ?? 8 }}/{{ $period->report_margin_bottom_mm ?? 8 }} mm</p>
                <p><span class="block text-xs text-slate-600">Tempat dan Tanggal</span>{{ $period->report_place ?: 'Belum diatur' }} · {{ ($period->report_date ?? $period->ends_on)?->translatedFormat('d F Y') }}</p>
                <p><span class="block text-xs text-slate-600">Skala Isi</span>{{ $period->report_scale_percent ?? 90 }}%</p>
            </div>
        @endif
    </section>

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

    <script>
        document.querySelector('[data-report-class-picker]')?.addEventListener('change', (event) => {
            window.location.assign(event.target.value);
        });
    </script>
@endsection
