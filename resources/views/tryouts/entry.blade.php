@extends('layouts.tryout')

@section('title', $trialExam->title)

@section('content')
    <div class="mx-auto max-w-xl">
        <section class="rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.04] p-7">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-300">Akses peserta</p>
            <h1 class="mt-3 text-3xl font-semibold">{{ $trialExam->title }}</h1>
            <p class="mt-3 text-slate-400">{{ $trialExam->questionBank->subject->name }} · Kelas {{ $trialExam->questionBank->grade_level }} · {{ $trialExam->duration_minutes }} menit · {{ $questionCount }} soal PG</p>
        </section>

        @if ($errors->any())<div class="mt-5 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>@endif

        @if ($available)
            <div class="mt-5 rounded-3xl border border-amber-400/25 bg-amber-400/[0.06] p-6">
                <p class="font-semibold text-amber-200">Peringatan sebelum memulai ujian</p>
                <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-slate-300">
                    <li>Layar penuh wajib. Dilarang berpindah tab/aplikasi, membuka bubble, split screen, bilah notifikasi, tombol kembali, atau keluar dari halaman.</li>
                    <li>Pelanggaran pertama mendapat peringatan; pelanggaran kedua mengunci ujian. Waktu tetap berjalan.</li>
                    <li>Aktifkan mode Jangan Ganggu dan tutup aplikasi lain sebelum mulai.</li>
                    <li>Bobot maksimal PG adalah 70 poin: 70 ÷ {{ $questionCount }} = sekitar {{ number_format($questionCount > 0 ? 70 / $questionCount : 0, 4, ',', '.') }} poin per jawaban benar.</li>
                </ul>
            </div>
            <form method="POST" action="{{ route('tryout.start', $trialExam) }}" class="mt-5 space-y-4 rounded-3xl border border-white/10 bg-white/[0.035] p-7">
                @csrf
                <label class="block text-sm text-slate-300">Nama lengkap<input name="participant_name" value="{{ old('participant_name') }}" required autocomplete="name" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3"></label>
                <label class="block text-sm text-slate-300">NIS/NISN (opsional)<input name="participant_identifier" value="{{ old('participant_identifier') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3"></label>
                <label class="block text-sm text-slate-300">Kode ujian<input name="access_code" required autocomplete="off" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 uppercase tracking-wider" placeholder="SJR-SMKBHG"></label>
                <label class="flex items-start gap-2 text-xs text-slate-300"><input type="checkbox" required class="mt-0.5">Saya sudah membaca dan menyetujui aturan ujian.</label>
                <button class="w-full rounded-xl bg-cyan-400 px-4 py-3 font-semibold text-slate-950">Mulai ujian uji coba</button>
                <p class="text-center text-xs text-slate-500">Gunakan satu perangkat dan jangan memuat ulang halaman setelah ujian dimulai.</p>
            </form>
        @else
            <div class="mt-5 rounded-3xl border border-amber-400/20 bg-amber-400/10 p-6 text-sm text-amber-100">Ujian uji coba belum dibuka atau sudah ditutup. Hubungi panitia.</div>
        @endif
    </div>
@endsection

