@extends('layouts.app')

@section('title', 'Ujian Saya')
@section('eyebrow', 'Portal Siswa')
@section('heading', 'Jadwal dan Pelaksanaan Ujian')

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.045] p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Identitas peserta</p>
        <h2 class="mt-2 text-2xl font-semibold text-white">{{ $student->full_name }}</h2>
        <p class="mt-2 text-sm text-slate-400">NIS {{ $student->student_number }} · {{ $student->schoolClass->name }}</p>
        <div class="mt-4 inline-flex rounded-full px-3 py-1 text-xs {{ $checkin?->status->value === 'verified' ? 'bg-emerald-400/10 text-emerald-200' : 'bg-amber-400/10 text-amber-200' }}">
            {{ $checkin?->status->value === 'verified' ? 'Absensi hari ini terverifikasi' : 'Belum melakukan absensi hari ini' }}
        </div>
    </section>

    <section class="mt-8 space-y-4" aria-labelledby="core-exam-schedule-title">
        <div>
            <h2 id="core-exam-schedule-title" class="text-xl font-semibold text-white">Jadwal Ujian Inti</h2>
        </div>
        @if ($exams->isNotEmpty())
            <details id="core-exam-rules" class="rounded-2xl border border-white/10 p-3">
                <summary class="cursor-pointer px-2 py-1 text-sm font-semibold text-cyan-300">Aturan dan panduan ujian</summary>
                <x-exam-rules core class="mt-3" />
            </details>
        @endif
        @php
            $statusLabels = [
                'ready' => 'Siap dimulai', 'checkin' => 'Belum mulai · Absen dulu',
                'working' => 'Sedang dikerjakan', 'done' => 'Selesai', 'waiting' => 'Menunggu jadwal',
                'locked' => 'Ujian terkunci', 'terminated' => 'Ujian dihentikan', 'blocked' => 'Perlu bantuan',
                'expired' => 'Waktu habis', 'closed' => 'Jadwal berakhir', 'absent' => 'Tidak hadir', 'cancelled' => 'Dibatalkan',
            ];
        @endphp
        @forelse ($exams as ['assignment' => $assignment, 'attendanceReason' => $attendanceReason, 'state' => $state])
            @php
                $session = $assignment->examSession;
                $attempt = $assignment->attempt;
                $canAttend = $attendanceReason === null;
                $hasCheckin = $checkin?->status->value === 'verified' && $checkin->campus_id === $session->campus_id;
                $attendanceError = (int) session('attendance_assignment_id') === (int) $assignment->id
                    ? $errors->getBag('attendance')->first() : '';
                $checkinProblem = $checkin?->status->value === 'rejected'
                    || ($checkin?->status->value === 'verified' && ! $hasCheckin);
            @endphp
            <article id="attendance-{{ $assignment->id }}" data-exam-state="{{ $state }}" class="student-exam-card scroll-mt-24 rounded-2xl border p-4 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-cyan-300">{{ $assignment->assessmentSubject->assessmentPeriod->name }}</p>
                        <h3 class="mt-2 text-xl font-semibold text-white">{{ $assignment->assessmentSubject->subject->name }}</h3>
                        <p class="mt-2 text-sm text-slate-400">{{ $session->starts_at->format('d/m/Y H:i') }}–{{ $session->ends_at->format($session->starts_at->isSameDay($session->ends_at) ? 'H:i' : 'd/m/Y H:i') }} · {{ $session->duration_minutes }} menit</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $session->campus->name }}{{ $session->room_name ? ' · '.$session->room_name : '' }} · {{ $session->kind->value === 'makeup' ? 'Susulan' : 'Reguler' }}</p>
                    </div>
                    <span class="student-exam-status inline-flex rounded-full px-3 py-1 text-xs font-semibold">{{ $statusLabels[$state] }}</span>
                </div>

                @if (! $hasCheckin && $canAttend && in_array($state, ['checkin', 'waiting'], true))
                    <form method="POST" action="{{ route('student.attendance.store', $assignment) }}" data-core-attendance class="mt-5 rounded-2xl border border-amber-400/20 bg-amber-400/[0.045] p-4">
                        @csrf
                        <input type="hidden" name="latitude">
                        <input type="hidden" name="longitude">
                        <input type="hidden" name="accuracy_meters">
                        <p class="text-sm font-semibold text-amber-200">Absensi lokasi ujian · GPS wajib</p>
                        <p class="mt-2 text-sm leading-6 text-slate-300">Izinkan lokasi presisi saat browser meminta izin. Tunggu sampai absensi terverifikasi sebelum mulai ujian.</p>
                        <button type="submit" data-location-check class="mt-4 w-full rounded-xl bg-amber-400 px-4 py-3 text-sm font-semibold text-slate-950 disabled:opacity-60 sm:w-auto">Ambil lokasi &amp; kirim absensi</button>
                        <x-attendance-feedback :state="$attendanceError !== '' ? 'error' : 'pending'"
                            :message="$attendanceError ?: 'Ambil lokasi untuk memverifikasi absensi sebelum memulai ujian.'" class="mt-3" />
                        <a href="#core-exam-location-rules" onclick="document.getElementById('core-exam-rules').open = true" class="mt-2 inline-block text-sm font-semibold text-cyan-300 underline">Panduan jika absensi gagal</a>
                    </form>
                @elseif ($attendanceError !== '')
                    <x-attendance-feedback state="error" :message="$attendanceError" class="mt-5" />
                @endif

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    @if ($state === 'locked')
                        <a href="{{ route('student.exams.show', $attempt) }}" class="rounded-xl bg-amber-400 px-5 py-3 text-sm font-semibold text-slate-950">Lihat status dan hubungi pengawas</a>
                    @elseif ($state === 'working')
                        <a href="{{ route('student.exams.show', $attempt) }}" class="student-exam-action rounded-xl px-5 py-3 text-sm font-semibold">Lanjutkan ujian</a>
                    @elseif ($state === 'done')
                        <p class="text-sm text-slate-300">Jawaban sudah dikumpulkan.</p>
                    @elseif ($state === 'expired')
                        <a href="{{ route('student.exams.show', $attempt) }}" class="rounded-xl border border-white/20 px-5 py-3 text-sm font-semibold text-slate-300">Lihat status ujian</a>
                    @elseif ($state === 'terminated')
                        <p class="text-sm text-rose-300">Hubungi pengawas ujian.</p>
                    @elseif ($state === 'absent' || $state === 'cancelled' || $state === 'closed')
                        <p class="text-sm text-slate-400">Hubungi panitia jika memerlukan ujian susulan.</p>
                    @elseif ($state === 'ready')
                        <form method="POST" action="{{ route('student.exams.start', $assignment) }}" onsubmit="return confirm('Mulai ujian sekarang? Waktu akan langsung berjalan.')" class="w-full space-y-3">
                            @csrf
                            <x-attendance-feedback state="success" message="Lokasi terverifikasi. Centang pernyataan kesiapan, lalu pilih Mulai ujian." />
                            @if ($assignment->assessmentSubject->has_multiple_choice)
                                <p class="text-sm text-slate-300">Setiap jawaban PG benar bernilai tetap 2 poin. Nilai PG maksimal 100.</p>
                            @endif
                            <a href="#core-exam-rules-title" onclick="document.getElementById('core-exam-rules').open = true" class="inline-block text-sm font-semibold text-cyan-300 underline">Baca kembali peringatan ujian</a>
                            <label class="flex items-start gap-3 text-sm leading-6 text-slate-300">
                                <input type="checkbox" required aria-describedby="core-exam-rules-title" class="mt-1.5 shrink-0">
                                <span>Saya sudah membaca dan memahami aturan serta siap memulai ujian.</span>
                            </label>
                            <button type="submit" class="student-exam-action rounded-xl px-5 py-3 text-sm font-semibold">Mulai ujian</button>
                        </form>
                    @elseif ($attendanceReason && $attendanceError === '')
                        <x-attendance-feedback :state="$checkinProblem || $state === 'blocked' ? 'error' : 'pending'"
                            :title="$state === 'waiting' ? 'Menunggu jadwal' : null" :message="$attendanceReason" class="w-full" />
                    @elseif ($hasCheckin && $state === 'waiting')
                        <x-attendance-feedback state="pending" title="Menunggu jadwal"
                            :message="'Absensi sudah terverifikasi. Ujian dapat dimulai pukul '.$session->starts_at->format('H:i').'.'" class="w-full" />
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-3xl border border-dashed border-white/10 p-12 text-center text-sm text-slate-500">Belum ada ujian yang ditugaskan kepada Anda.</div>
        @endforelse
    </section>

    <script src="{{ asset('js/exam-attendance.js') }}?v=2" defer></script>
@endsection
