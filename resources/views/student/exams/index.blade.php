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
                    <form method="POST" action="{{ route('student.attendance.store', $assignment) }}" enctype="multipart/form-data" class="attendance-form mt-5 grid gap-3 rounded-2xl border border-amber-400/20 bg-amber-400/[0.045] p-4 md:grid-cols-2">
                        @csrf
                        <input type="hidden" name="latitude">
                        <input type="hidden" name="longitude">
                        <input type="hidden" name="accuracy_meters">
                        <select name="method" required class="attendance-method rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                            <option value="face">Selfie + lokasi</option>
                            <option value="card_face">Kartu + selfie + lokasi</option>
                        </select>
                        <input name="card_uid" placeholder="Tempel/masukkan UID kartu" class="card-input hidden rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                        <input type="file" name="selfie" accept="image/jpeg,image/png,image/webp" capture="user" required class="rounded-xl border border-dashed border-white/15 bg-slate-950/50 px-3 py-2 text-xs text-slate-400 file:mr-2 file:rounded-lg file:border-0 file:bg-amber-400 file:px-3 file:py-2 file:font-semibold file:text-slate-950">
                        <button class="attendance-button rounded-xl bg-amber-400 px-4 py-3 text-sm font-semibold text-slate-950">Ambil lokasi & kirim absensi</button>
                        <p class="attendance-message text-xs leading-5 text-slate-500 md:col-span-2">Izinkan kamera dan GPS presisi tinggi. Fitur lokasi membutuhkan HTTPS atau localhost.</p>
                    </form>
                @endif

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    @if ($attempt?->status->value === 'in_progress' && $attempt->security_locked_at)
                        <a href="{{ route('student.exams.show', $attempt) }}" class="rounded-xl bg-amber-400 px-5 py-3 text-sm font-semibold text-slate-950">Ujian terkunci — hubungi pengawas</a>
                    @elseif ($attempt?->status->value === 'in_progress')
                        <a href="{{ route('student.exams.show', $attempt) }}" class="rounded-xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950">Lanjutkan ujian</a>
                    @elseif ($attempt?->status->value === 'submitted')
                        <span class="rounded-xl bg-emerald-400/10 px-5 py-3 text-sm font-medium text-emerald-200">Ujian sudah dikumpulkan</span>
                    @elseif ($attempt?->status->value === 'terminated')
                        <span class="text-sm text-rose-300">Ujian dihentikan. Hubungi pengawas.</span>
                    @elseif ($attendanceReason)
                        <span class="text-sm text-amber-300">{{ $attendanceReason }}</span>
                    @elseif ($isOpen && $hasCheckin)
                        <form method="POST" action="{{ route('student.exams.start', $assignment) }}" onsubmit="return confirm('Mulai ujian sekarang? Waktu akan langsung berjalan.')">
                            @csrf
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
            const method = form.querySelector('.attendance-method');
            const card = form.querySelector('.card-input');
            method.addEventListener('change', () => {
                card.classList.toggle('hidden', method.value !== 'card_face');
                card.required = method.value === 'card_face';
            });

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
