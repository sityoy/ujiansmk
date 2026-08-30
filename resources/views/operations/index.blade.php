@extends('layouts.app')

@section('title', 'Pelaksanaan Ujian')
@section('eyebrow', 'Ruang Kendali')
@section('heading', 'Pelaksanaan Ujian')

@section('content')
    @php($statusLabels = ['draft' => 'Draf', 'published' => 'Terbit', 'active' => 'Aktif', 'closed' => 'Ditutup'])

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Sesi aktif', $statistics['activeSessions'], 'text-cyan-300'],
            ['Sedang mengerjakan', $statistics['startedAttempts'], 'text-amber-300'],
            ['Sudah dikumpulkan', $statistics['submittedAttempts'], 'text-emerald-300'],
            ['Total pelanggaran', $statistics['violations'], 'text-rose-300'],
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
            <p class="mt-2 text-sm text-slate-500">Saat sesi ditutup, seluruh ujian yang masih berlangsung dikumpulkan otomatis.</p>
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
                        <form method="POST" action="{{ route('operations.sessions.status', $session) }}" class="flex items-center gap-2">
                            @csrf @method('PATCH')
                            <select name="status" class="rounded-lg border border-white/10 bg-slate-950 px-2 py-2 text-xs">
                                @foreach ($sessionStatuses as $status)<option value="{{ $status->value }}" @selected($session->status === $status)>{{ $statusLabels[$status->value] }}</option>@endforeach
                            </select>
                            <button class="text-xs font-medium text-cyan-300">Simpan</button>
                        </form>
                    </div>
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
            <p class="mt-1 text-sm text-slate-500">Pemantauan peserta, waktu terakhir aktif, nilai, dan pelanggaran.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-4">Siswa</th><th class="px-5 py-4">Mapel</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Terakhir aktif</th><th class="px-5 py-4">Nilai</th><th class="px-5 py-4">Pelanggaran</th></tr></thead>
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
    </section>
@endsection
