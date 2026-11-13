@extends('layouts.app')

@section('title', 'Bank Soal Uji Coba')
@section('eyebrow', 'Simulasi Mandiri')
@section('heading', 'Bank Soal Uji Coba')

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.045] p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Terpisah dari Ujian Inti</p>
        <h2 class="mt-2 text-2xl font-semibold text-white">Bank khusus latihan dan simulasi</h2>
        <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-400">Soal di sini hanya digunakan oleh Ujian Uji Coba. Setiap bank berlaku untuk satu mapel dan satu tingkat: Kelas 10, 11, atau 12, tanpa dibedakan berdasarkan rombel.</p>
    </section>

    <section id="try-out-packs" class="mt-6 rounded-3xl border border-emerald-400/20 bg-emerald-400/[0.04] p-5">
        <h2 class="text-lg font-semibold text-white">Pilih Try Out yang ingin ditambahkan</h2>
        <p class="mt-2 text-sm leading-6 text-slate-400">Setiap pilihan berisi 20 soal PG A–E beserta kunci: 6 mudah, 8 sedang, dan 6 sulit. Klik satu pilihan untuk menambahkannya ke Bank Soal Uji Coba.</p>
        <form method="GET" action="{{ route('trial-question-banks.index') }}#try-out-packs" class="mt-4 grid gap-3 sm:grid-cols-[160px_1fr_auto]">
            @if ($archived)<input type="hidden" name="archived" value="1">@endif
            @if ($gradeLevel)<input type="hidden" name="grade_level" value="{{ $gradeLevel }}">@endif
            @if ($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
            <label class="text-sm text-slate-300">Kelas Try Out
                <select name="pack_grade_level" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                    <option value="">Semua kelas</option>
                    @foreach ($gradeLevels as $level)<option value="{{ $level }}" @selected($packGrade === $level)>Kelas {{ $level }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm text-slate-300">Cari mata pelajaran
                <input name="pack_search" value="{{ $packSearch }}" placeholder="Bahasa Indonesia, Matematika, ..." class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
            </label>
            <button class="self-end rounded-xl bg-emerald-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Tampilkan pilihan</button>
        </form>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($packs as $pack)
                <article class="flex flex-col rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                    <h3 class="font-semibold text-white">{{ $pack['name'] }}</h3>
                    @if (! $pack['ready'])
                        <p class="mt-2 flex-1 text-xs leading-5 text-slate-400">Mapel sekolah terdaftar. Isi 20 soal untuk pilihan ini belum disiapkan.</p>
                        <button type="button" disabled class="mt-4 rounded-xl border border-white/10 px-3 py-2.5 text-sm text-slate-500">Soal belum tersedia</button>
                    @elseif ($pack['bank']?->archived_at)
                        <p class="mt-2 flex-1 text-xs text-amber-300">Pernah ditambahkan, lalu dihapus.</p>
                        <a href="{{ route('trial-question-banks.index', ['archived' => 1, 'q' => $pack['bank']->name]) }}" class="mt-4 rounded-xl border border-amber-400/20 px-3 py-2.5 text-center text-sm text-amber-200">Lihat untuk dipulihkan</a>
                    @elseif ($pack['bank'])
                        <p class="mt-2 flex-1 text-xs text-emerald-300">Sudah ditambahkan · {{ $pack['bank']->questions_count }} soal</p>
                        <a href="{{ route('trial-question-banks.questions.index', $pack['bank']) }}" class="mt-4 rounded-xl border border-emerald-400/20 px-3 py-2.5 text-center text-sm text-emerald-200">Buka soal</a>
                    @else
                        <p class="mt-2 flex-1 text-xs text-slate-400">20 soal · Mudah → sedang → sulit</p>
                        <form method="POST" action="{{ route('trial-question-banks.import-pack') }}" class="mt-4">
                            @csrf
                            <input type="hidden" name="pack_key" value="{{ $pack['id'] }}">
                            <button class="w-full rounded-xl bg-emerald-400 px-3 py-2.5 text-sm font-semibold text-slate-950">Tambahkan {{ $pack['name'] }}</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="text-sm text-slate-400">Tidak ada pilihan yang sesuai pencarian.</p>
            @endforelse
        </div>
    </section>

    <nav class="mt-6 flex gap-3 text-sm" aria-label="Status bank soal">
        <a href="{{ route('trial-question-banks.index') }}" class="rounded-xl border border-white/10 px-4 py-2 {{ ! $archived ? 'text-cyan-300' : 'text-slate-400' }}">Bank aktif</a>
        <a href="{{ route('trial-question-banks.index', ['archived' => 1]) }}" class="rounded-xl border border-white/10 px-4 py-2 {{ $archived ? 'text-cyan-300' : 'text-slate-400' }}">Dihapus / pulihkan</a>
    </nav>

    <div class="mt-6 grid gap-6 xl:grid-cols-[360px_1fr]">
        <form method="POST" action="{{ route('trial-question-banks.store') }}" class="space-y-4 rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            @csrf
            <div><h2 class="text-lg font-semibold text-white">Buat bank uji coba</h2><p class="mt-1 text-xs text-slate-500">Soal yang didukung: pilihan ganda A–E.</p></div>
            <label class="block text-sm text-slate-300">Nama bank
                <input name="name" value="{{ old('name') }}" required placeholder="Latihan Sejarah Semester Ganjil" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            </label>
            <label class="block text-sm text-slate-300">Mata pelajaran
                <select name="subject_id" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                    <option value="">Pilih mapel</option>
                    @foreach ($subjects as $subject)<option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>@endforeach
                </select>
            </label>
            <label class="block text-sm text-slate-300">Tingkat
                <select name="grade_level" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                    <option value="">Pilih tingkat</option>
                    @foreach ($gradeLevels as $level)<option value="{{ $level }}" @selected(old('grade_level') == $level)>Kelas {{ $level }}</option>@endforeach
                </select>
            </label>
            <button class="w-full rounded-xl bg-cyan-400 px-4 py-3 font-semibold text-slate-950">Buat dan isi soal</button>
        </form>

        <div>
            <form method="GET" class="grid gap-3 rounded-2xl border border-white/10 bg-white/[0.025] p-4 md:grid-cols-[180px_1fr_auto_auto]">
                @if ($archived)<input type="hidden" name="archived" value="1">@endif
                <input type="hidden" name="pack_grade_level" value="{{ $packGrade }}">
                <input type="hidden" name="pack_search" value="{{ $packSearch }}">
                <select name="grade_level" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                    <option value="">Semua tingkat</option>
                    @foreach ($gradeLevels as $level)<option value="{{ $level }}" @selected($gradeLevel === $level)>Kelas {{ $level }}</option>@endforeach
                </select>
                <input name="q" value="{{ $search }}" placeholder="Cari nama bank atau mapel" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                <button class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Cari</button>
                <a href="{{ route('trial-question-banks.index') }}" class="rounded-xl border border-white/10 px-4 py-2.5 text-center text-sm text-slate-300">Reset</a>
            </form>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @forelse ($banks as $bank)
                    <article class="flex flex-col rounded-3xl border border-white/10 bg-white/[0.035] p-5">
                        <div class="flex items-start justify-between gap-3"><span class="rounded-full bg-cyan-400/10 px-3 py-1 text-xs text-cyan-200">Kelas {{ $bank->grade_level }}</span><span class="text-xs text-slate-500">{{ $bank->questions_count }} soal</span></div>
                        <h3 class="mt-4 text-lg font-semibold text-white">{{ $bank->name }}</h3>
                        <p class="mt-1 text-sm text-slate-400">{{ $bank->subject->name }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ $bank->trial_exams_count }} ujian uji coba{{ $bank->teacher ? ' · Guru: '.$bank->teacher->name : '' }}</p>
                        <div class="mt-5 flex gap-2">
                            <a href="{{ route('trial-question-banks.questions.index', $bank) }}" class="flex-1 rounded-xl bg-cyan-400 px-4 py-2.5 text-center text-sm font-semibold text-slate-950">Kelola soal</a>
                            @if ($bank->archived_at)
                                <form method="POST" action="{{ route('trial-question-banks.restore', $bank) }}">@csrf @method('PATCH')<button class="rounded-xl border border-emerald-400/20 px-3 py-2.5 text-sm text-emerald-300">Pulihkan</button></form>
                            @else
                                <form method="POST" action="{{ route('trial-question-banks.destroy', $bank) }}" onsubmit="return confirm('Hapus bank dari daftar aktif dan nonaktifkan tautan uji cobanya? Soal serta riwayat peserta tetap disimpan dan bank dapat dipulihkan.')">@csrf @method('DELETE')<button class="rounded-xl border border-rose-400/20 px-3 py-2.5 text-sm text-rose-300">Hapus</button></form>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-dashed border-white/10 p-10 text-center text-sm text-slate-500 md:col-span-2">{{ $archived ? 'Belum ada bank soal yang dihapus.' : 'Belum ada bank soal uji coba.' }}</div>
                @endforelse
            </div>
            @if ($banks->hasPages())<div class="mt-5 rounded-2xl border border-white/10 p-4">{{ $banks->links() }}</div>@endif
        </div>
    </div>
@endsection

