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

    <section class="mt-6">
        <div class="mb-4">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">Ujian Uji Coba</p>
            <h2 class="mt-2 text-xl font-semibold text-white">Latihan yang tersedia untuk kelas Anda</h2>
            <p class="mt-1 text-sm text-slate-500">Identitas peserta mengikuti akun siswa. Nilai uji coba tidak ditampilkan.</p>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            @forelse ($trialExams as $trialExam)
                @php
                    $trialAttempt = $trialAttempts->get($trialExam->id);
                @endphp
                <article class="rounded-3xl border border-violet-400/20 bg-violet-400/[0.04] p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-violet-300">{{ $trialExam->questionBank->subject->name }} · Kelas {{ $trialExam->questionBank->grade_level }}</p>
                    <h3 class="mt-2 text-xl font-semibold text-white">{{ $trialExam->title }}</h3>
                    <p class="mt-2 text-sm text-slate-400">{{ $trialExam->duration_minutes }} menit · Setiap PG benar 2 poin · Sistem pengamanan aktif</p>
                    <div class="mt-5">
                        @if ($trialAttempt?->status === 'submitted')
                            <span class="inline-flex rounded-xl bg-emerald-400/10 px-4 py-3 text-sm font-medium text-emerald-200">Uji coba sudah diselesaikan</span>
                        @elseif ($trialAttempt?->status === 'in_progress')
                            <a href="{{ route('tryout.show', [$trialExam, $trialAttempt]) }}" class="inline-flex rounded-xl bg-violet-400 px-4 py-3 text-sm font-semibold text-slate-950">{{ $trialAttempt->security_locked_at ? 'Ujian terkunci — buka status' : 'Lanjutkan uji coba' }}</a>
                        @else
                            <a href="{{ route('tryout.entry', $trialExam) }}" class="inline-flex rounded-xl bg-violet-400 px-4 py-3 text-sm font-semibold text-slate-950">Buka ujian uji coba</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-white/10 p-8 text-center text-sm text-slate-500 lg:col-span-2">Belum ada Ujian Uji Coba aktif untuk tingkat kelas Anda.</div>
            @endforelse
        </div>
    </section>

    <section class="mt-8 space-y-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Ujian Inti</p>
            <h2 class="mt-2 text-xl font-semibold text-white">Jadwal dan pelaksanaan ujian</h2>
        </div>
        @if ($assignments->isNotEmpty())
            <x-exam-rules core />
        @endif
        @forelse ($assignments as $assignment)
            @php
                $session = $assignment->examSession;
                $attempt = $assignment->attempt;
                $isOpen = in_array($session->status->value, ['published', 'active'], true)
                    && now()->gte($session->starts_at)
                    && now()->lt($session->ends_at);
                $attendanceReason = app(\App\Services\Attendance\AttendanceAvailability::class)->reason($assignment, $checkin);
                $canAttend = $attendanceReason === null;
                $hasCheckin = $checkin?->status->value === 'verified' && $checkin->campus_id === $session->campus_id;
                $attendanceError = (int) session('attendance_assignment_id') === (int) $assignment->id
                    ? $errors->getBag('attendance')->first() : '';
                $checkinProblem = $checkin?->status->value === 'rejected'
                    || ($checkin?->status->value === 'verified' && ! $hasCheckin);
                $pgCount = $assignment->assessmentSubject->questions
                    ->filter(fn ($question) => $question->question_type->value === 'multiple_choice')->count();
            @endphp
            <article id="attendance-{{ $assignment->id }}" class="scroll-mt-24 rounded-3xl border border-white/10 bg-white/[0.035] p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-cyan-300">{{ $assignment->assessmentSubject->assessmentPeriod->name }}</p>
                        <h3 class="mt-2 text-xl font-semibold text-white">{{ $assignment->assessmentSubject->subject->name }}</h3>
                        <p class="mt-2 text-sm text-slate-400">{{ $session->starts_at->format('d/m/Y H:i') }}–{{ $session->ends_at->format($session->starts_at->isSameDay($session->ends_at) ? 'H:i' : 'd/m/Y H:i') }} · {{ $session->duration_minutes }} menit</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $session->campus->name }}{{ $session->room_name ? ' · '.$session->room_name : '' }} · {{ $session->kind->value === 'makeup' ? 'Susulan' : 'Reguler' }}</p>
                    </div>
                    <span class="rounded-full border border-white/10 px-3 py-1 text-xs text-slate-300">{{ ucfirst($assignment->status->value) }}</span>
                </div>

                @if (! $hasCheckin && $canAttend && ! $attempt)
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
                        <a href="#core-exam-location-rules" class="mt-2 inline-block text-sm font-semibold text-cyan-300 underline">Panduan jika absensi gagal</a>
                    </form>
                @elseif ($attendanceError !== '')
                    <x-attendance-feedback state="error" :message="$attendanceError" class="mt-5" />
                @endif

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    @if ($attempt?->status->value === 'in_progress' && $attempt->security_locked_at)
                        <a href="{{ route('student.exams.show', $attempt) }}" class="rounded-xl bg-amber-400 px-5 py-3 text-sm font-semibold text-slate-950">Ujian terkunci — hubungi pengawas</a>
                    @elseif ($attempt?->status->value === 'in_progress')
                        <a href="{{ route('student.exams.show', $attempt) }}" class="rounded-xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950">Lanjutkan ujian</a>
                    @elseif ($attempt?->status->value === 'submitted')
                        <span class="rounded-xl bg-emerald-400/10 px-5 py-3 text-sm font-medium text-emerald-200">Ujian sudah dikumpulkan. Nilai tidak ditampilkan kepada peserta.</span>
                    @elseif ($attempt?->status->value === 'terminated')
                        <span class="text-sm text-rose-300">Ujian dihentikan. Hubungi pengawas.</span>
                    @elseif ($attendanceReason)
                        @if ($attendanceError === '')
                            <x-attendance-feedback :state="$checkinProblem ? 'error' : 'pending'"
                                :title="$checkinProblem ? null : 'Belum bisa mulai'" :message="$attendanceReason" class="w-full" />
                        @endif
                    @elseif ($isOpen && $hasCheckin)
                        <form method="POST" action="{{ route('student.exams.start', $assignment) }}" onsubmit="return confirm('Mulai ujian sekarang? Waktu akan langsung berjalan.')" class="w-full space-y-3">
                            @csrf
                            <x-attendance-feedback state="success" message="Lokasi terverifikasi. Centang pernyataan kesiapan, lalu pilih Mulai ujian." />
                            @if ($pgCount > 0)
                                <p class="text-sm text-slate-300">Setiap jawaban PG benar bernilai tetap 2 poin. Nilai PG maksimal 100.</p>
                            @endif
                            <a href="#core-exam-rules-title" class="inline-block text-sm font-semibold text-cyan-300 underline">Baca kembali peringatan ujian</a>
                            <label class="flex items-start gap-3 text-sm leading-6 text-slate-300">
                                <input type="checkbox" required aria-describedby="core-exam-rules-title" class="mt-1.5 shrink-0">
                                <span>Saya sudah membaca dan memahami aturan serta siap memulai ujian.</span>
                            </label>
                            <button type="submit" class="attendance-start rounded-xl px-5 py-3 text-sm font-semibold">Mulai ujian</button>
                        </form>
                    @elseif ($hasCheckin && ! $isOpen)
                        <x-attendance-feedback state="pending" title="Menunggu jadwal"
                            :message="'Absensi sudah terverifikasi. Ujian dapat dimulai pukul '.$session->starts_at->format('H:i').'.'" class="w-full" />
                    @elseif (! $isOpen)
                        <span class="text-sm text-slate-500">Ujian dimulai pukul {{ $session->starts_at->format('H:i') }}. Absensi dapat diselesaikan terlebih dahulu.</span>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-3xl border border-dashed border-white/10 p-12 text-center text-sm text-slate-500">Belum ada ujian yang ditugaskan kepada Anda.</div>
        @endforelse
    </section>

    <script src="{{ asset('js/exam-attendance.js') }}?v=2" defer></script>
@endsection
