@extends('layouts.tryout')

@section('title', $trialExam->title)

@section('content')
    <div class="mx-auto max-w-xl">
        <section class="rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.04] p-7">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-300">Akses peserta</p>
            <h1 class="mt-3 text-3xl font-semibold">{{ $trialExam->title }}</h1>
            <p class="mt-3 text-slate-400">{{ $trialExam->questionBank->subject->name }} · Kelas {{ $trialExam->questionBank->grade_level }} · {{ $trialExam->duration_minutes }} menit · {{ $questionCount }} soal</p>
        </section>

        @php($locationError = $available && $trialExam->location ? $errors->first('location') : '')
        @if ($errors->any() && $locationError === '')
            <x-attendance-feedback state="error" title="Belum bisa mulai" :message="$errors->first()" class="mt-5" />
        @endif

        @unless ($available)
            <div class="mt-5 rounded-3xl border border-amber-400/20 bg-amber-400/10 p-6 text-sm text-amber-100">Ujian uji coba belum dibuka atau sudah ditutup. Hubungi panitia.</div>
        @endunless

        @if ($available && ! $trialExam->location && $errors->isEmpty())
            <x-attendance-feedback state="error" title="Belum bisa mulai"
                message="Lokasi GPS ujian belum disiapkan. Hubungi pengawas." class="mt-5" />
        @endif

        <x-exam-rules class="mt-5" location-required />

        @if ($available && $trialExam->location)
            <form method="POST" action="{{ route('tryout.start', $trialExam) }}" class="mt-5 space-y-4 rounded-3xl border border-white/10 bg-white/[0.035] p-5 sm:p-7"
                data-trial-attendance-url="{{ route('tryout.attendance', $trialExam) }}">
                @csrf
                @if ($pgCount > 0)
                    <p class="text-sm text-slate-300">Setiap soal PG bernilai 2 poin jika pilihan sesuai kunci. Nilai PG maksimal 100.</p>
                @endif
                @if ($shortCount > 0 || $essayCount > 0)
                    <p class="text-sm text-slate-300">{{ collect([
                        $shortCount > 0 ? $shortCount.' isian singkat' : null,
                        $essayCount > 0 ? $essayCount.' esai' : null,
                    ])->filter()->join(' dan ') }} akan dikoreksi manual.</p>
                @endif
                @if (auth()->user()?->student)
                    <div class="rounded-xl border border-cyan-400/20 bg-cyan-400/[0.05] p-4">
                        <p class="text-xs text-slate-400">Peserta dari akun yang sedang login</p>
                        <p class="mt-1 font-semibold text-cyan-200">{{ auth()->user()->student->full_name }}</p>
                        <p class="mt-1 text-xs text-slate-500">NIS/NISN diambil otomatis dari akun siswa.</p>
                    </div>
                @else
                    <label class="block text-sm text-slate-300">NIS atau NISN
                        <input name="participant_identifier" value="{{ old('participant_identifier') }}" required autocomplete="username" inputmode="numeric" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" placeholder="Masukkan NIS/NISN terdaftar">
                        <span class="mt-2 block text-xs leading-5 text-slate-500">Nama peserta diambil otomatis dari data siswa. NIS/NISN harus sesuai dengan tingkat kelas ujian.</span>
                    </label>
                @endif
                <label class="block text-sm text-slate-300">Kode ujian<input name="access_code" value="{{ old('access_code') }}" required autocomplete="off" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 uppercase tracking-wider"></label>
                <section class="space-y-3 rounded-xl border border-cyan-400/20 p-4" aria-label="Absensi lokasi simulasi">
                    <p class="font-semibold text-cyan-200">GPS wajib · {{ $trialExam->location->name }}</p>
                    <p class="text-sm text-slate-300">Aktifkan lokasi presisi dan pilih Izinkan jika diminta. Waktu ujian berjalan setelah Anda menekan Mulai ujian dan verifikasi berhasil.</p>
                    <button type="button" data-location-check class="rounded-xl border border-cyan-400/30 px-4 py-3 text-sm font-semibold text-cyan-200 disabled:opacity-60">Ambil Lokasi &amp; Absen Simulasi</button>
                    <x-attendance-feedback :state="$locationError !== '' ? 'error' : 'pending'"
                        :message="$locationError ?: 'Isi identitas dan kode, lalu ambil lokasi untuk memverifikasi absensi.'" />
                    <a href="#trial-exam-location-rules" class="inline-block text-sm font-semibold text-cyan-300 underline">Panduan jika absensi gagal</a>
                    <noscript><p class="text-sm text-amber-200">Aktifkan JavaScript agar browser dapat memverifikasi lokasi.</p></noscript>
                </section>
                <script src="{{ asset('js/exam-attendance.js') }}?v=2" defer></script>
                <a href="#trial-exam-rules-title" class="inline-block text-sm font-semibold text-cyan-300 underline">Baca kembali peringatan ujian</a>
                <label class="flex items-start gap-3 text-sm leading-6 text-slate-300">
                    <input type="checkbox" required aria-describedby="trial-exam-rules-title" class="mt-1.5 shrink-0">
                    <span>Saya sudah membaca dan memahami aturan serta siap memulai ujian.</span>
                </label>
                <button type="submit" disabled class="attendance-start w-full rounded-xl px-4 py-3 font-semibold">Mulai ujian uji coba</button>
            </form>
        @endif
    </div>
@endsection

