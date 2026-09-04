@extends('layouts.app')

@section('title', 'Pelaksanaan Ujian')
@section('eyebrow', 'Ruang Kendali')
@section('heading', 'Pelaksanaan Ujian')

@section('content')
    @php($statusLabels = ['draft' => 'Draf', 'published' => 'Terbit', 'active' => 'Aktif', 'closed' => 'Ditutup'])

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    <form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-white/10 bg-white/[0.035] p-4 md:grid-cols-4">
        <label class="text-xs text-slate-400">Tahun ajaran
            <select name="academic_year_id" class="mt-2 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                <option value="">Semua tahun ajaran</option>
                @foreach ($academicYears as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? '') == $year->id)>{{ $year->name }}</option>@endforeach
            </select>
        </label>
        <label class="text-xs text-slate-400">Tanggal sesi
            <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="mt-2 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
        </label>
        <label class="text-xs text-slate-400">Status sesi
            <select name="status" class="mt-2 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                <option value="">Semua status</option>
                @foreach ($sessionStatuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $statusLabels[$status->value] }}</option>@endforeach
            </select>
        </label>
        <div class="flex items-end gap-3"><button class="rounded-lg bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Terapkan</button><a href="{{ route('operations.index') }}" class="py-2.5 text-sm text-slate-400">Reset</a></div>
        <p class="text-xs text-slate-500 md:col-span-4">Ringkasan dan aktivitas mengikuti filter. Data dimuat {{ now()->format('d/m/Y H:i:s') }}; muat ulang untuk melihat pembaruan.</p>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Sesi aktif', $statistics['activeSessions'], 'text-cyan-300'],
            ['Sedang mengerjakan', $statistics['startedAttempts'], 'text-amber-300'],
            ['Sudah dikumpulkan', $statistics['submittedAttempts'], 'text-emerald-300'],
            ['Insiden tercatat', $statistics['violations'], 'text-rose-300'],
        ] as [$label, $value, $color])
            <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-5">
                <p class="text-sm text-slate-400">{{ $label }}</p>
                <p class="mt-2 text-3xl font-semibold {{ $color }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <section class="mt-6 rounded-3xl border border-white/10 bg-white/[0.035] p-6">
        <div class="mb-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Sesi</p>
            <h2 class="mt-2 text-xl font-semibold text-white">Kontrol status sesi ujian</h2>
            <p class="mt-2 text-sm text-slate-500">Penutupan mengumpulkan jawaban dan menandai peserta yang belum mulai sebagai tidak hadir ujian jika waktu mulai sudah tercapai. Sesi tidak dapat dibuka ulang.</p>
        </div>
        <div class="grid gap-3 xl:grid-cols-2">
            @forelse ($sessions as $session)
                <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-white">{{ $session->assessmentSubject->subject->name }} · {{ $session->assessmentSubject->schoolClass->name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $session->assessmentSubject->assessmentPeriod->name }} · {{ $session->kind->value === 'makeup' ? 'Susulan' : 'Reguler' }}</p>
                            <p class="mt-2 text-sm text-slate-300">{{ $session->starts_at->format('d/m/Y H:i') }}–{{ $session->ends_at->format('H:i') }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $session->campus->name }}{{ $session->room_name ? ' · '.$session->room_name : '' }} · {{ $session->assignments_count }} peserta</p>
                        </div>
                        <form method="POST" action="{{ route('operations.sessions.status', $session) }}" class="flex items-center gap-2" onsubmit="return this.elements.status.value !== 'closed' || confirm('Tutup sesi? Jawaban dikumpulkan dan peserta yang belum mulai ditandai tidak hadir ujian setelah waktu mulai. Sesi tidak dapat dibuka ulang.')">
                            @csrf @method('PATCH')
                            <select name="status" @disabled($session->status->value === 'closed') class="rounded-lg border border-white/10 bg-slate-950 px-2 py-2 text-xs">
                                @foreach ($sessionStatuses as $status)<option value="{{ $status->value }}" @selected($session->status === $status)>{{ $statusLabels[$status->value] }}</option>@endforeach
                            </select>
                            <button @disabled($session->status->value === 'closed') class="text-xs font-medium text-cyan-300 disabled:opacity-40">Simpan</button>
                        </form>
                    </div>
                    <a href="{{ route('operations.sessions.show', $session) }}" class="mt-4 inline-flex rounded-lg border border-cyan-400/30 px-3 py-2 text-xs font-semibold text-cyan-300">Lihat peserta & progres</a>
                </div>
            @empty
                <p class="text-sm text-slate-500">Belum ada sesi ujian. Buat jadwal terlebih dahulu.</p>
            @endforelse
        </div>
        @if ($sessions->hasPages())<div class="mt-5">{{ $sessions->links() }}</div>@endif
    </section>

    <section class="mt-6 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
        <div class="border-b border-white/10 p-6">
            <h2 class="text-xl font-semibold text-white">Aktivitas pengerjaan terbaru</h2>
            <p class="mt-1 text-sm text-slate-500">Waktu simpan terakhir bukan penanda siswa sedang online. Insiden browser perlu diperiksa pengawas, bukan otomatis bukti kecurangan.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-4">Siswa</th><th class="px-5 py-4">Mapel</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Simpan terakhir</th><th class="px-5 py-4">Nilai</th><th class="px-5 py-4">Insiden</th></tr></thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($attempts as $attempt)
                        <tr>
                            <td class="px-5 py-4 font-medium text-white">{{ $attempt->assignment->student->full_name }}</td>
                            <td class="px-5 py-4 text-slate-400">{{ $attempt->assignment->assessmentSubject->subject->name }}</td>
                            <td class="px-5 py-4 text-slate-300">{{ str($attempt->status->value)->replace('_', ' ')->title() }}</td>
                            <td class="px-5 py-4 text-slate-400">{{ $attempt->last_seen_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
                            <td class="px-5 py-4 text-slate-300">{{ $attempt->score ?? '—' }}</td>
                            <td class="px-5 py-4 {{ $attempt->violation_count ? 'text-rose-300' : 'text-slate-500' }}">{{ $attempt->violation_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">Belum ada aktivitas pengerjaan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($attempts->hasPages())<div class="p-5">{{ $attempts->links() }}</div>@endif
    </section>
@endsection
