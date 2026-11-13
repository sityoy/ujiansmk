@extends('layouts.app')

@section('title', 'Absensi & Keamanan')
@section('eyebrow', 'Pemantauan per Mapel')
@section('heading', 'Absensi & Keamanan')

@section('content')
    @php
        $statusLabels = ['verified' => 'Terverifikasi', 'review' => 'Perlu diperiksa', 'rejected' => 'Ditolak'];
        $methodLabels = ['card_face' => 'Kartu + wajah', 'card' => 'Kartu', 'face' => 'Wajah', 'manual' => 'Manual', 'location' => 'Lokasi GPS'];
        $incidentLabels = ['tab_hidden' => 'Berpindah tab/aplikasi', 'fullscreen_exit' => 'Keluar layar penuh', 'window_blur' => 'Fokus hilang', 'system_gesture' => 'Membuka bilah sistem', 'viewport_change' => 'Perubahan layar/split screen', 'restricted_action' => 'Tombol terlarang', 'navigation_attempt' => 'Mencoba keluar'];
    @endphp

    @if ($errors->any())<div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>@endif

    <form method="GET" class="grid gap-3 rounded-2xl border border-white/10 bg-white/[0.035] p-4 md:grid-cols-[1fr_1fr_auto_auto]">
        <input type="date" name="date" value="{{ $filters['date'] }}" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
        <select name="assessment_period_id" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
            <option value="">Semua periode</option>
            @foreach ($periodOptions as $period)<option value="{{ $period->id }}" @selected(($filters['assessment_period_id'] ?? '') == $period->id)>{{ $period->name }} · {{ $period->academicYear->name }}</option>@endforeach
        </select>
        <button class="rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950">Tampilkan</button>
        <a href="{{ route('attendance.index') }}" class="rounded-xl border border-white/10 px-4 py-3 text-center text-sm text-slate-300">Hari ini</a>
    </form>

    <section class="mt-6">
        <div class="mb-4"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Pilih mapel/sesi</p><p class="mt-1 text-sm text-slate-500">Hanya sesi pada tanggal dan periode terpilih yang ditampilkan.</p></div>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($sessions as $session)
                <a href="{{ route('attendance.index', array_filter(['date' => $filters['date'], 'assessment_period_id' => $filters['assessment_period_id'] ?? null, 'session_id' => $session->id])) }}"
                    class="rounded-2xl border p-4 {{ $selectedSession?->id === $session->id ? 'border-cyan-400/50 bg-cyan-400/10' : 'border-white/10 bg-white/[0.035] hover:border-cyan-400/20' }}">
                    <p class="font-semibold text-white">{{ $session->assessmentSubject->subject->name }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $session->assessmentSubject->schoolClass->name }} · {{ $session->assessmentSubject->assessmentPeriod->name }}</p>
                    <p class="mt-2 text-xs text-slate-500">{{ $session->starts_at->format('H:i') }}–{{ $session->ends_at->format('H:i') }} · {{ $session->campus->name }}{{ $session->room_name ? ' · '.$session->room_name : '' }}</p>
                    <p class="mt-2 text-xs text-cyan-200">{{ $session->attempts_count }}/{{ $session->assignments_count }} peserta mulai</p>
                </a>
            @empty
                <p class="text-sm text-slate-500">Tidak ada sesi pada filter ini.</p>
            @endforelse
        </div>
        @if ($sessions->hasPages())<div class="mt-4">{{ $sessions->links() }}</div>@endif
    </section>

    @if ($selectedSession)
        <section class="mt-6 rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.04] p-6">
            <p class="text-xs uppercase tracking-wider text-cyan-300">Mapel terpilih</p>
            <h2 class="mt-2 text-xl font-semibold">{{ $selectedSession->assessmentSubject->subject->name }} · {{ $selectedSession->assessmentSubject->schoolClass->name }}</h2>
            <p class="mt-2 text-sm text-slate-400">{{ $selectedSession->assessmentSubject->assessmentPeriod->name }} · {{ $selectedSession->starts_at->format('d/m/Y H:i') }}–{{ $selectedSession->ends_at->format('H:i') }}</p>
        </section>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['Terverifikasi', $statistics['verified'], 'text-emerald-300'], ['Perlu diperiksa', $statistics['review'], 'text-amber-300'], ['Ditolak', $statistics['rejected'], 'text-rose-300'], ['Insiden', $statistics['incidents'], 'text-violet-300']] as [$label, $value, $color])
                <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4"><p class="text-xs text-slate-400">{{ $label }}</p><p class="mt-2 text-2xl font-semibold {{ $color }}">{{ $value }}</p></div>
            @endforeach
        </div>

        <section class="mt-6 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
            <div class="border-b border-white/10 p-5"><h2 class="text-lg font-semibold">Absensi peserta mapel ini</h2></div>
            <div class="overflow-x-auto"><table class="min-w-full text-left text-sm">
                <thead class="bg-slate-950/60 text-xs uppercase text-slate-500"><tr><th class="px-5 py-4">Siswa</th><th class="px-5 py-4">Metode</th><th class="px-5 py-4">Jarak/Akurasi</th><th class="px-5 py-4">Status</th></tr></thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($checkins as $checkin)
                        <tr><td class="px-5 py-4"><p class="font-medium text-white">{{ $checkin->student->full_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $checkin->student->schoolClass->name }}</p></td>
                            <td class="px-5 py-4 text-slate-400">{{ $methodLabels[$checkin->method->value] }}@if($checkin->selfie_path)<br><a href="{{ route('attendance.selfie', $checkin) }}" target="_blank" class="text-xs text-cyan-300">Lihat selfie</a>@endif</td>
                            <td class="px-5 py-4 text-slate-400">{{ number_format((float) $checkin->distance_meters, 0) }} m / ±{{ number_format((float) $checkin->accuracy_meters, 0) }} m</td>
                            <td class="px-5 py-4"><form method="POST" action="{{ route('attendance.status', $checkin) }}" class="flex items-center gap-2">@csrf @method('PATCH')
                                <select name="status" class="rounded-lg border border-white/10 bg-slate-950 px-2 py-2 text-xs">@foreach($checkinStatuses as $status)<option value="{{ $status->value }}" @selected($checkin->status === $status)>{{ $statusLabels[$status->value] }}</option>@endforeach</select><button class="text-xs text-cyan-300">Simpan</button>
                            </form></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-10 text-center text-slate-500">Belum ada absensi untuk peserta sesi ini.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @if($checkins->hasPages())<div class="p-5">{{ $checkins->links() }}</div>@endif
        </section>

        <section class="mt-6 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
            <div class="border-b border-white/10 p-5"><h2 class="text-lg font-semibold">Insiden keamanan mapel ini</h2></div>
            <div class="divide-y divide-white/5">
                @forelse ($incidents as $incident)
                    <div class="flex flex-wrap items-center justify-between gap-3 p-5"><div><p class="font-medium text-white">{{ $incident->examAttempt->assignment->student->full_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $incidentLabels[$incident->category] ?? str($incident->category)->replace('_', ' ')->title() }} · {{ $incident->occurred_at->format('d/m/Y H:i:s') }}</p></div><span class="rounded-full bg-rose-400/10 px-2.5 py-1 text-xs text-rose-200">Level {{ $incident->severity }}</span></div>
                @empty
                    <p class="p-6 text-sm text-slate-500">Belum ada insiden pada sesi ini.</p>
                @endforelse
            </div>
            @if($incidents->hasPages())<div class="p-5">{{ $incidents->links() }}</div>@endif
        </section>
    @endif
@endsection
