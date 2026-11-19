@extends('layouts.app')

@section('title', 'Ujian Uji Coba')
@section('eyebrow', 'Simulasi berbasis kode')
@section('heading', 'Ujian Uji Coba')

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    <a href="{{ route('trial-locations.index') }}" class="mb-5 inline-flex rounded-xl border border-cyan-400/30 px-4 py-2.5 text-sm font-semibold text-cyan-200">Lokasi Simulasi</a>

    @if ($trialExams->total() > 0)
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-cyan-400/20 bg-cyan-400/[0.04] px-5 py-4">
            <div>
                <p class="text-sm font-semibold text-white">Kartu gabungan seluruh Ujian Uji Coba</p>
                <p class="mt-1 text-xs leading-5 text-slate-400">Satu kartu per siswa berisi seluruh tautan dan kode Uji Coba yang sesuai dengan tingkat kelasnya.</p>
            </div>
            <a href="{{ route('tryouts.cards.all') }}" target="_blank" class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Cetak kartu semua link</a>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[380px_1fr]">
        @if ($banks->isNotEmpty())
        <form method="POST" action="{{ route('tryouts.store') }}" class="space-y-4 rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            @csrf
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Buat tautan baru</p>
                <h2 class="mt-2 text-xl font-semibold">Ujian tanpa akun siswa</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">Peserta membuka tautan khusus, memasukkan NIS/NISN dan kode bersama, lalu mengerjakan soal PG.</p>
            </div>
            <label class="block text-sm text-slate-300">Bank soal uji coba
                <select name="trial_question_bank_id" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                    <option value="">Pilih bank soal</option>
                    @foreach ($banks as $bank)
                        <option value="{{ $bank->id }}" @selected(old('trial_question_bank_id') == $bank->id)>
                            {{ $bank->name }} · {{ $bank->subject->name }} · Kelas {{ $bank->grade_level }} ({{ $bank->questions_count }} PG)
                        </option>
                    @endforeach
                </select>
                <a href="{{ route('trial-question-banks.index') }}" class="mt-2 inline-block text-xs text-cyan-300">Kelola bank soal uji coba →</a>
            </label>
            @include('tryouts.settings-fields', ['trial' => null])
            <button class="w-full rounded-xl bg-cyan-400 px-4 py-3 font-semibold text-slate-950">Buat ujian uji coba</button>
        </form>
        @else
            <section class="rounded-3xl border border-emerald-400/20 bg-emerald-400/[0.05] p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">Konfigurasi lengkap</p>
                <h2 class="mt-2 text-xl font-semibold">Semua bank soal sudah dibuatkan ujian</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">Bank soal yang sudah digunakan tidak dapat dibuat menjadi Ujian Uji Coba kedua. Kelola ujian yang tersedia di daftar sebelah kanan.</p>
                <a href="{{ route('trial-question-banks.index') }}" class="mt-4 inline-block text-sm font-semibold text-cyan-300">Kelola bank soal uji coba →</a>
            </section>
        @endif

        <section class="space-y-4">
            @forelse ($trialExams as $trial)
                <article class="rounded-3xl border border-white/10 bg-white/[0.035] p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold text-white">{{ $trial->title }}</h2>
                                <span class="rounded-full px-2.5 py-1 text-xs {{ $trial->is_active ? 'bg-emerald-400/10 text-emerald-300' : 'bg-slate-400/10 text-slate-400' }}">{{ $trial->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-400">{{ $trial->questionBank->subject->name }} · Kelas {{ $trial->questionBank->grade_level }} · {{ $trial->questionBank->name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $trial->duration_minutes }} menit · {{ $trial->attempts_count }} peserta · Nilai tidak ditampilkan kepada peserta</p>
                            @if ($trial->location)
                                <p class="mt-1 text-xs text-emerald-300">GPS wajib · {{ $trial->location->name }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('tryouts.participants', $trial) }}" class="rounded-xl bg-violet-400/10 px-3 py-2 text-xs font-semibold text-violet-200">Lihat peserta</a>
                            <a href="{{ route('tryouts.edit', $trial) }}" class="rounded-xl border border-cyan-400/30 px-3 py-2 text-xs font-semibold text-cyan-200">Edit</a>
                            <x-trial-score-link :trial="$trial" />
                            @if ($trial->trial_location_id)
                                <a href="{{ route('tryouts.attendance.print', $trial) }}" class="rounded-xl border border-emerald-400/30 px-3 py-2 text-xs font-semibold text-emerald-200">Laporan absensi</a>
                            @endif
                            @unless ($trial->questionBank->archived_at)
                            <a href="{{ route('tryouts.cards', $trial) }}" target="_blank" class="rounded-xl bg-cyan-400/10 px-3 py-2 text-xs font-semibold text-cyan-200">Cetak kartu</a>
                            <a href="{{ route('exam-flyers.trial', $trial) }}" target="_blank" rel="noopener" class="rounded-xl bg-emerald-400/10 px-3 py-2 text-xs font-semibold text-emerald-200">Cetak flyer</a>
                            @endunless
                            @if ($trial->questionBank->archived_at)<span class="text-xs text-amber-300">Bank dihapus · riwayat tersimpan</span>@else
                            <form method="POST" action="{{ route('tryouts.toggle', $trial) }}">@csrf @method('PATCH')<button class="rounded-xl border border-white/10 px-3 py-2 text-xs text-cyan-200">{{ $trial->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                            @endif
                            <form method="POST" action="{{ route('tryouts.destroy', $trial) }}" onsubmit="return confirm('Hapus ujian uji coba ini?')">@csrf @method('DELETE')<button class="rounded-xl border border-rose-400/20 px-3 py-2 text-xs text-rose-300">Hapus</button></form>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_auto]">
                        <div class="rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-slate-300">
                            <span class="text-slate-500">Tautan:</span> <a target="_blank" href="{{ route('tryout.entry', $trial) }}" class="break-all text-cyan-300">{{ route('tryout.entry', $trial) }}</a>
                        </div>
                        <div class="rounded-xl border border-amber-400/20 bg-amber-400/10 px-4 py-3 text-sm"><span class="text-amber-200">Kode:</span> <strong class="ml-2 tracking-wider text-white">{{ $trial->access_code }}</strong></div>
                    </div>
                    @if($trial->starts_at || $trial->ends_at)<p class="mt-3 text-xs text-slate-500">Jadwal: {{ $trial->starts_at?->format('d/m/Y H:i') ?? 'sekarang' }} – {{ $trial->ends_at?->format('d/m/Y H:i') ?? 'tanpa batas' }}</p>@endif
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-white/10 p-10 text-center text-sm text-slate-500">Belum ada ujian uji coba.</div>
            @endforelse
            {{ $trialExams->links() }}
        </section>
    </div>
@endsection

