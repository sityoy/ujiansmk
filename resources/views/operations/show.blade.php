@extends('layouts.app')

@section('title', 'Peserta Sesi Ujian')
@section('eyebrow', 'Pemantauan Sesi')
@section('heading', $session->assessmentSubject->subject->name.' · '.$session->assessmentSubject->schoolClass->name)

@section('content')
    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-rose-400/30 bg-rose-400/10 p-4 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif
    @php
        $sessionLabels = ['draft' => 'Draf', 'published' => 'Terbit', 'active' => 'Aktif', 'closed' => 'Ditutup'];
        $checkinLabels = ['verified' => 'Terverifikasi', 'review' => 'Perlu diperiksa', 'rejected' => 'Ditolak'];
        $questionCount = $session->assessmentSubject->questions_count;
    @endphp
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('operations.index') }}" class="text-sm text-cyan-300">← Pelaksanaan ujian</a>
        <a href="{{ request()->fullUrl() }}" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Muat ulang data</a>
    </div>
    <section class="rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.045] p-6">
        <p class="text-xs uppercase tracking-wider text-cyan-300">{{ $session->assessmentSubject->assessmentPeriod->name }} · {{ $session->assessmentSubject->assessmentPeriod->academicYear->name }}</p>
        <h2 class="mt-3 text-xl font-semibold">{{ $session->assessmentSubject->subject->name }} · {{ $session->assessmentSubject->schoolClass->name }}</h2>
        <p class="mt-2 text-sm text-slate-300">{{ $session->starts_at->format('d/m/Y H:i') }}–{{ $session->ends_at->format('d/m/Y H:i') }} · {{ $session->duration_minutes }} menit · {{ $sessionLabels[$session->status->value] }}</p>
        <p class="mt-2 text-sm text-slate-400">{{ $session->campus->name }}{{ $session->room_name ? ' · '.$session->room_name : '' }} · {{ $session->kind->value === 'makeup' ? 'Susulan' : 'Reguler' }} · {{ $questionCount }} soal</p>
        @if (!$questionCount || !$statistics['total'] || !$session->campus->is_active)
            <p class="mt-4 text-sm text-amber-300">Perlu dilengkapi: {{ collect([!$questionCount ? 'bank soal masih kosong' : null, !$statistics['total'] ? 'belum ada peserta ditempatkan' : null, !$session->campus->is_active ? 'lokasi tidak aktif' : null])->filter()->implode('; ') }}.</p>
        @endif
        <p class="mt-4 text-xs text-slate-500">Data dimuat {{ now()->format('d/m/Y H:i:s') }} ({{ config('app.timezone') }}). Halaman ini tidak memperbarui otomatis. Absensi di bawah sesuai tanggal dan lokasi sesi, bukan selalu hari ini.</p>
    </section>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        @foreach (['total' => 'Total peserta', 'scheduled' => 'Belum mulai', 'in_progress' => 'Berjalan (termasuk terkunci)', 'locked' => 'Perlu pemeriksaan', 'submitted' => 'Dikumpulkan', 'absent' => 'Tidak hadir ujian'] as $key => $label)
            <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4"><p class="text-xs text-slate-400">{{ $label }}</p><p class="mt-2 text-2xl font-semibold text-cyan-300">{{ $statistics[$key] }}</p></div>
        @endforeach
    </div>

    <section class="mt-6 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
        <div class="border-b border-white/10 p-5">
            <h2 class="text-lg font-semibold">Peserta yang ditempatkan di sesi ini</h2>
            <p class="mt-2 text-xs leading-5 text-slate-400">Progres hanya menghitung jawaban yang sudah tersimpan di server. Simpan terakhir bukan indikator online; insiden browser tetap perlu pemeriksaan pengawas. Ringkasan di atas tetap mencakup seluruh sesi meski daftar difilter.</p>
            <form method="GET" class="mt-4 flex flex-wrap gap-3">
                <input name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Cari nama, NIS, atau NISN" aria-label="Cari peserta" class="min-w-0 flex-1 rounded-xl border border-white/10 bg-slate-950 px-4 py-2.5 text-sm">
                <select name="status" aria-label="Status peserta" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                    <option value="">Semua status</option>
                    @foreach ($statusLabels as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach
                </select>
                <button class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Cari</button>
                <a href="{{ route('operations.sessions.show', $session) }}" class="px-2 py-2.5 text-sm text-slate-400">Reset</a>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-950/60 text-xs uppercase text-slate-500"><tr><th class="px-5 py-4">Peserta</th><th class="px-5 py-4">Absensi sesi</th><th class="px-5 py-4">Status ujian</th><th class="px-5 py-4">Progres</th><th class="px-5 py-4">Waktu</th><th class="px-5 py-4">Nilai</th><th class="px-5 py-4">Insiden</th></tr></thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($participants as $assignment)
                        @php
                            $attempt = $assignment->attempt;
                            $status = $attempt?->status->value ?? $assignment->status->value;
                            if ($status === 'in_progress' && $attempt->security_locked_at) $status = 'locked';
                            $checkin = $assignment->student->dailyCheckins->first();
                            $answered = $attempt?->answers_count ?? 0;
                        @endphp
                        <tr>
                            <td class="px-5 py-4"><p class="font-medium text-white">{{ $assignment->student->full_name }}</p><p class="mt-1 text-xs text-slate-500">NIS {{ $assignment->student->student_number }} · NISN {{ $assignment->student->nisn ?: '—' }}</p></td>
                            <td class="px-5 py-4 text-xs {{ $checkin?->status->value === 'verified' ? 'text-emerald-300' : 'text-amber-300' }}">{{ $checkin ? $checkinLabels[$checkin->status->value] : 'Belum tercatat' }}</td>
                            <td class="px-5 py-4 text-slate-300">{{ $statusLabels[$status] ?? 'Perlu pemeriksaan' }}</td>
                            <td class="px-5 py-4 text-slate-300"><p>{{ $answered }} / {{ $questionCount }} jawaban</p><progress value="{{ $answered }}" max="{{ max(1, $questionCount) }}" aria-label="Progres jawaban {{ $assignment->student->full_name }}" class="mt-2 h-2 w-24 accent-cyan-400"></progress></td>
                            <td class="whitespace-nowrap px-5 py-4 text-xs leading-6 text-slate-400">Mulai: {{ $attempt?->started_at?->format('H:i:s') ?? '—' }}<br>Simpan terakhir: {{ $attempt?->last_seen_at?->format('H:i:s') ?? '—' }}<br>Dikumpulkan: {{ $attempt?->submitted_at?->format('H:i:s') ?? '—' }}</td>
                            <td class="px-5 py-4 text-slate-300">{{ $attempt?->score ?? '—' }}</td>
                            <td class="min-w-72 px-5 py-4 text-slate-300">
                                <p>{{ $attempt?->violation_count ?? 0 }}{{ $attempt?->security_enabled ? '/2' : '' }}</p>
                                @if ($attempt)
                                    <details class="mt-2 text-xs">
                                        <summary class="cursor-pointer text-cyan-300">Riwayat pengawasan</summary>
                                        @forelse ($attempt->securityIncidents as $incident)
                                            <p class="mt-3 leading-5">{{ $incident->occurred_at->format('d/m H:i:s') }} · {{ ['tab_hidden' => 'Halaman tidak terlihat', 'fullscreen_exit' => 'Keluar layar penuh', 'supervisor_resume' => 'Diizinkan lanjut', 'supervisor_submit' => 'Dikumpulkan pengawas'][$incident->category] ?? $incident->category }}
                                                @if (array_key_exists('counted', $incident->details ?? [])) · {{ $incident->details['counted'] ? 'Dihitung' : 'Sinyal bersamaan; tidak dihitung ulang' }} @endif
                                                @if (isset($incident->details['reason']))<br>{{ $incident->details['reviewer_name'] ?? 'Pengawas' }}: {{ $incident->details['reason'] }}@endif
                                            </p>
                                        @empty
                                            <p class="mt-2 text-slate-500">Belum ada catatan.</p>
                                        @endforelse
                                    </details>
                                    @if ($status === 'locked')
                                        <form method="POST" action="{{ route('operations.attempts.security-review', $attempt) }}" class="mt-3 space-y-2" onsubmit="return confirm('Simpan keputusan pengawas? Waktu dan riwayat tidak direset.')">
                                            @csrf
                                            <input type="hidden" name="lock_version" value="{{ $attempt->security_lock_version }}">
                                            <label class="block text-xs text-amber-300">Alasan pemeriksaan (wajib)
                                                <textarea name="reason" required minlength="5" maxlength="1000" rows="2" class="mt-1 w-full rounded-lg border border-white/20 bg-slate-950 p-2 text-white"></textarea>
                                            </label>
                                            <select name="action" required aria-label="Keputusan pengawas" class="w-full rounded-lg border border-white/20 bg-slate-950 p-2 text-xs">
                                                <option value="">Pilih keputusan</option>
                                                <option value="resume">Izinkan lanjut tanpa reset</option>
                                                <option value="submit">Akhiri dan kumpulkan jawaban</option>
                                            </select>
                                            <p class="text-xs leading-5 text-slate-400">Jika diizinkan lanjut, hitungan tetap 2/2 dan kejadian berikutnya langsung mengunci kembali.</p>
                                            <button class="rounded-lg bg-amber-400 px-3 py-2 text-xs font-semibold text-slate-950">Simpan keputusan</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">Tidak ada peserta sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-5"><p class="mb-3 text-xs text-slate-500">{{ $participants->total() }} peserta sesuai filter · 25 peserta per halaman.</p>{{ $participants->links() }}</div>
    </section>
    @if (in_array(auth()->user()->role->value, ['super_admin', 'committee'], true))
        <p class="mt-5 text-sm text-slate-400">Peserta tidak hadir dapat dipindahkan melalui <a href="{{ route('scheduling.index') }}#peserta-susulan" class="text-cyan-300">Penjadwalan → Peserta Susulan</a>. Penugasan lama dipindahkan, bukan dibuat ganda; siswa yang sudah mulai tidak dapat dipindahkan.</p>
    @endif
@endsection
