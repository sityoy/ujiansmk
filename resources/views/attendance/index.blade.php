@extends('layouts.app')

@section('title', 'Absensi & Keamanan')
@section('eyebrow', 'Verifikasi Kehadiran')
@section('heading', 'Absensi & Keamanan')

@section('content')
    @php
        $statusLabels = ['verified' => 'Terverifikasi', 'review' => 'Perlu diperiksa', 'rejected' => 'Ditolak'];
        $methodLabels = ['card_face' => 'Kartu + wajah', 'card' => 'Kartu', 'face' => 'Wajah', 'manual' => 'Manual'];
    @endphp

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Terverifikasi hari ini', $statistics['verified'], 'text-emerald-300'],
            ['Perlu diperiksa', $statistics['review'], 'text-amber-300'],
            ['Ditolak', $statistics['rejected'], 'text-rose-300'],
            ['Insiden hari ini', $statistics['incidents'], 'text-violet-300'],
        ] as [$label, $value, $color])
            <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-5"><p class="text-sm text-slate-400">{{ $label }}</p><p class="mt-2 text-3xl font-semibold {{ $color }}">{{ $value }}</p></div>
        @endforeach
    </div>

    <section class="mt-6 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
        <div class="border-b border-white/10 p-6">
            <h2 class="text-xl font-semibold text-white">Absensi hari ini</h2>
            <p class="mt-1 text-sm text-slate-500">Satu verifikasi per siswa per hari dengan pemeriksaan radius dan akurasi GPS.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-4">Siswa</th><th class="px-5 py-4">Metode</th><th class="px-5 py-4">Lokasi</th><th class="px-5 py-4">Jarak/Akurasi</th><th class="px-5 py-4">Status</th></tr></thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($checkins as $checkin)
                        <tr>
                            <td class="px-5 py-4"><p class="font-medium text-white">{{ $checkin->student->full_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $checkin->student->schoolClass->name }}</p></td>
                            <td class="px-5 py-4 text-slate-400">
                                <span class="block">{{ $methodLabels[$checkin->method->value] }}</span>
                                @if ($checkin->selfie_path)
                                    <a href="{{ route('attendance.selfie', $checkin) }}" target="_blank" rel="noopener" class="mt-1 inline-flex text-xs text-cyan-300">Lihat selfie</a>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-400">{{ $checkin->campus->name }}</td>
                            <td class="px-5 py-4 text-slate-400">{{ number_format((float) $checkin->distance_meters, 0) }} m / ±{{ number_format((float) $checkin->accuracy_meters, 0) }} m</td>
                            <td class="px-5 py-4">
                                <form method="POST" action="{{ route('attendance.status', $checkin) }}" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <select name="status" class="rounded-lg border border-white/10 bg-slate-950 px-2 py-2 text-xs">
                                        @foreach ($checkinStatuses as $status)<option value="{{ $status->value }}" @selected($checkin->status === $status)>{{ $statusLabels[$status->value] }}</option>@endforeach
                                    </select>
                                    <button class="text-xs text-cyan-300">Simpan</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">Belum ada absensi hari ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($checkins->hasPages())<div class="border-t border-white/10 p-5">{{ $checkins->links() }}</div>@endif
    </section>

    <section class="mt-6 rounded-3xl border border-white/10 bg-white/[0.035] p-6">
        <h2 class="text-xl font-semibold text-white">Insiden keamanan terbaru</h2>
        <div class="mt-5 grid gap-3 lg:grid-cols-2">
            @forelse ($incidents as $incident)
                <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                    <div class="flex items-center justify-between gap-3"><span class="text-sm font-medium text-white">{{ str($incident->category)->replace('_', ' ')->title() }}</span><span class="rounded-full bg-rose-400/10 px-2.5 py-1 text-xs text-rose-200">Level {{ $incident->severity }}</span></div>
                    <p class="mt-2 text-sm text-slate-400">{{ $incident->examAttempt->assignment->student->full_name }} · {{ $incident->examAttempt->assignment->assessmentSubject->subject->name }}</p>
                    <p class="mt-1 text-xs text-slate-600">{{ $incident->occurred_at->format('d/m/Y H:i:s') }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-500">Belum ada insiden keamanan.</p>
            @endforelse
        </div>
    </section>
@endsection
