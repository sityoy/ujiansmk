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

    <section class="mt-6 space-y-4">
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
                $pgCount = $assignment->assessmentSubject->questions
                    ->filter(fn ($question) => $question->question_type->value === 'multiple_choice')->count();
                $pgWeight = $pgCount > 0 ? 70 / $pgCount : 0;
            @endphp
            <article class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
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
                    <form method="POST" action="{{ route('student.attendance.store', $assignment) }}" class="attendance-form mt-5 rounded-2xl border border-amber-400/20 bg-amber-400/[0.045] p-4">
                        @csrf
                        <input type="hidden" name="latitude">
                        <input type="hidden" name="longitude">
                        <input type="hidden" name="accuracy_meters">
                        <p class="text-sm font-semibold text-amber-200">Absensi lokasi ujian</p>
                        <p class="mt-2 text-xs leading-5 text-slate-400">Tidak menggunakan selfie. Sistem hanya memeriksa GPS, akurasi, dan radius lokasi sekolah.</p>
                        <button class="attendance-button mt-4 w-full rounded-xl bg-amber-400 px-4 py-3 text-sm font-semibold text-slate-950 sm:w-auto">Ambil lokasi & kirim absensi</button>
                        <p class="attendance-message mt-3 text-xs leading-5 text-slate-500">Izinkan GPS presisi tinggi. Fitur lokasi membutuhkan HTTPS atau localhost.</p>
                    </form>
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
                        <span class="text-sm text-amber-300">{{ $attendanceReason }}</span>
                    @elseif ($isOpen && $hasCheckin)
                        <div class="w-full rounded-2xl border border-amber-400/25 bg-amber-400/[0.06] p-4 text-sm">
                            <p class="font-semibold text-amber-200">Peringatan sebelum memulai ujian</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs leading-5 text-slate-300">
                                <li>Layar penuh wajib. Jangan berpindah tab/aplikasi, membuka bubble, split screen, bilah notifikasi, tombol kembali, atau keluar dari halaman.</li>
                                <li>Pelanggaran pertama mendapat peringatan; pelanggaran kedua mengunci ujian. Waktu tetap berjalan.</li>
                                <li>Aktifkan mode Jangan Ganggu dan tutup aplikasi lain sebelum memulai.</li>
                                @if ($pgCount > 0)<li>Setiap jawaban PG benar bernilai tetap 2 poin. Nilai PG maksimal 100.</li>@endif
                            </ul>
                        </div>
                        <form method="POST" action="{{ route('student.exams.start', $assignment) }}" onsubmit="return confirm('Mulai ujian sekarang? Waktu akan langsung berjalan.')" class="space-y-3">
                            @csrf
                            <label class="flex items-start gap-2 text-xs text-slate-300"><input type="checkbox" required class="mt-0.5">Saya sudah membaca dan menyetujui aturan ujian.</label>
                            <button class="rounded-xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950">Mulai ujian</button>
                        </form>
                    @elseif (! $isOpen)
                        <span class="text-sm text-slate-500">Ujian dimulai pukul {{ $session->starts_at->format('H:i') }}. Absensi dapat diselesaikan terlebih dahulu.</span>
                    @else
                        <span class="text-sm text-amber-300">Selesaikan absensi terlebih dahulu.</span>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-3xl border border-dashed border-white/10 p-12 text-center text-sm text-slate-500">Belum ada ujian yang ditugaskan kepada Anda.</div>
        @endforelse
    </section>

    <script>
        document.querySelectorAll('.attendance-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.located === 'yes') return;
                event.preventDefault();
                const button = form.querySelector('.attendance-button');
                const message = form.querySelector('.attendance-message');

                if (!navigator.geolocation) {
                    message.textContent = 'Perangkat tidak mendukung pembacaan lokasi.';
                    return;
                }

                button.disabled = true;
                button.textContent = 'Membaca lokasi...';
                navigator.geolocation.getCurrentPosition((position) => {
                    form.latitude.value = position.coords.latitude;
                    form.longitude.value = position.coords.longitude;
                    form.accuracy_meters.value = position.coords.accuracy;
                    form.dataset.located = 'yes';
                    form.submit();
                }, (error) => {
                    button.disabled = false;
                    button.textContent = 'Ambil lokasi & kirim absensi';
                    message.textContent = 'Lokasi gagal dibaca: ' + error.message;
                }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
            });
        });
    </script>
@endsection
