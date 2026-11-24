@extends('layouts.app')

@section('title', 'Data Akademik')
@section('eyebrow', 'Master Data')
@section('heading', 'Data Akademik')

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">01 · Tahun Ajaran</p>
            <h2 class="mt-2 text-xl font-semibold text-white">Periode akademik sekolah</h2>

            <form method="POST" action="{{ route('academic.years.store') }}" class="mt-6 grid gap-3 sm:grid-cols-2">
                @csrf
                <input name="name" value="{{ old('name') }}" required placeholder="2026/2027"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400">
                <label class="flex items-center gap-2 rounded-xl border border-white/10 px-4 text-sm text-slate-300">
                    <input type="checkbox" name="is_active" value="1"> Jadikan aktif
                </label>
                <input type="date" name="starts_on" value="{{ old('starts_on') }}" required
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400">
                <input type="date" name="ends_on" value="{{ old('ends_on') }}" required
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400">
                <button class="sm:col-span-2 rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950">Tambah tahun ajaran</button>
            </form>

            <div class="mt-6 space-y-2">
                @forelse ($academicYears as $year)
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-slate-950/40 p-3">
                        <div>
                            <p class="text-sm font-medium text-white">{{ $year->name }}</p>
                            <p class="text-xs text-slate-500">{{ $year->classes_count }} kelas · {{ $year->starts_on->format('d/m/Y') }}–{{ $year->ends_on->format('d/m/Y') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($year->is_active)
                                <span class="rounded-full bg-emerald-400/10 px-2.5 py-1 text-xs text-emerald-200">Aktif</span>
                            @else
                                <form method="POST" action="{{ route('academic.years.activate', $year) }}">@csrf @method('PATCH')
                                    <button class="text-xs text-cyan-300">Aktifkan</button>
                                </form>
                                <form method="POST" action="{{ route('academic.years.destroy', $year) }}" onsubmit="return confirm('Hapus tahun ajaran ini?')">@csrf @method('DELETE')
                                    <button class="text-xs text-rose-300">Hapus</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada tahun ajaran.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">02 · Mata Pelajaran</p>
            <h2 class="mt-2 text-xl font-semibold text-white">Daftar mapel</h2>

            <form method="POST" action="{{ route('academic.subjects.store') }}" class="mt-6 grid gap-3 sm:grid-cols-[140px_1fr_auto]">
                @csrf
                <input name="code" value="{{ old('code') }}" required placeholder="Kode"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm uppercase outline-none focus:border-violet-400">
                <input name="name" value="{{ old('name') }}" required placeholder="Nama mata pelajaran"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-violet-400">
                <button class="rounded-xl bg-violet-400 px-4 py-3 text-sm font-semibold text-slate-950">Tambah</button>
            </form>

            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('academic.subjects.template') }}" class="rounded-lg border border-violet-400/30 px-3 py-2 text-xs font-medium text-violet-200 hover:bg-violet-400/10">Template Excel</a>
                <a href="{{ route('academic.subjects.export') }}" class="rounded-lg border border-white/10 px-3 py-2 text-xs font-medium text-slate-300 hover:bg-white/5">Ekspor mapel</a>
            </div>

            <form method="POST" action="{{ route('academic.subjects.import') }}" enctype="multipart/form-data" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                @csrf
                <input type="file" name="subject_spreadsheet" accept=".xlsx,.csv" required
                    class="block w-full rounded-xl border border-dashed border-white/15 bg-slate-950/50 px-3 py-2 text-xs text-slate-400 file:mr-2 file:rounded-lg file:border-0 file:bg-violet-400 file:px-3 file:py-2 file:font-semibold file:text-slate-950">
                <button class="rounded-xl border border-violet-400/30 px-4 py-2 text-xs font-semibold text-violet-200 hover:bg-violet-400/10">Impor mapel</button>
                <p class="sm:col-span-2 text-xs leading-5 text-slate-500">Kode yang sama akan diperbarui, bukan dibuat ganda. Maksimal 1.000 mapel.</p>
            </form>

            <div class="mt-6 max-h-80 space-y-2 overflow-y-auto pr-1">
                @forelse ($subjects as $subject)
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-slate-950/40 p-3">
                        <div>
                            <p class="text-sm font-medium text-white">{{ $subject->name }}</p>
                            <p class="text-xs text-slate-500">{{ $subject->code }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('academic.subjects.toggle', $subject) }}">@csrf @method('PATCH')
                                <button class="text-xs {{ $subject->is_active ? 'text-emerald-300' : 'text-slate-500' }}">{{ $subject->is_active ? 'Aktif' : 'Nonaktif' }}</button>
                            </form>
                            <form method="POST" action="{{ route('academic.subjects.destroy', $subject) }}" onsubmit="return confirm('Hapus mata pelajaran ini?')">@csrf @method('DELETE')
                                <button class="text-xs text-rose-300">Hapus</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada mata pelajaran.</p>
                @endforelse
            </div>
        </section>

        <section id="class-list" class="scroll-mt-5 rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-300">03 · Kelas/Rombel</p>
            <h2 class="mt-2 text-xl font-semibold text-white">Daftar kelas</h2>

            <form method="POST" action="{{ route('academic.classes.store') }}" class="mt-6 grid gap-3 sm:grid-cols-2">
                @csrf
                <select name="academic_year_id" required class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                    <option value="">Pilih tahun ajaran</option>
                    @foreach ($academicYears as $year)<option value="{{ $year->id }}">{{ $year->name }}</option>@endforeach
                </select>
                <input name="name" required placeholder="Contoh: IX-2"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-amber-400">
                <input type="number" min="1" max="13" name="grade_level" required placeholder="Tingkat kelas"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-amber-400">
                <input name="major" placeholder="Jurusan (opsional)"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-amber-400">
                <button class="sm:col-span-2 rounded-xl bg-amber-400 px-4 py-3 text-sm font-semibold text-slate-950">Tambah kelas</button>
            </form>

            <form method="GET" action="{{ route('academic.index') }}#class-list" class="mt-5 grid gap-2 sm:grid-cols-[1fr_140px_auto]">
                <select name="class_year" class="rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-xs">
                    <option value="">Semua tahun ajaran</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}" @selected((int) $classYearId === $year->id)>{{ $year->name }}</option>
                    @endforeach
                </select>
                <select name="class_per_page" class="rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-xs">
                    @foreach (\App\Support\TablePagination::OPTIONS as $value => $label)
                        <option value="{{ $value }}" @selected($classPerPage === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="rounded-xl border border-amber-400/30 px-4 py-2 text-xs font-semibold text-amber-200 hover:bg-amber-400/10">Tampilkan</button>
            </form>

            <div class="mt-6 space-y-2">
                @forelse ($classes as $class)
                    <div class="rounded-xl border border-white/10 bg-slate-950/40 p-3">
                        <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-white">{{ $class->name }}</p>
                            <p class="text-xs text-slate-500">{{ $class->academicYear->name }} · {{ $class->students_count }} siswa · Wali kelas: {{ $class->homeroomTeacher?->name ?? 'belum dipilih' }}</p>
                        </div>
                        <form method="POST" action="{{ route('academic.classes.destroy', $class) }}" onsubmit="return confirm('Hapus kelas ini?')">@csrf @method('DELETE')
                            <button class="text-xs text-rose-300">Hapus</button>
                        </form>
                        </div>
                        <form method="POST" action="{{ route('academic.classes.homeroom', $class) }}" class="mt-3 flex gap-2">@csrf @method('PATCH')
                            <select name="homeroom_teacher_user_id" class="min-w-0 flex-1 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-xs">
                                <option value="">Belum ada wali kelas</option>
                                @foreach ($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" @selected($class->homeroom_teacher_user_id === $teacher->id)>{{ $teacher->name }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-lg border border-amber-400/30 px-3 py-2 text-xs font-semibold text-amber-200">Simpan wali kelas</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada kelas.</p>
                @endforelse
            </div>

            <div class="mt-4">{{ $classes->links() }}</div>

        </section>

        <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">04 · Siswa</p>
            <h2 class="mt-2 text-xl font-semibold text-white">Tambah peserta didik</h2>

            <form method="POST" action="{{ route('academic.students.store') }}" class="mt-6 grid gap-3 sm:grid-cols-2">
                @csrf
                <select name="school_class_id" required class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                    <option value="">Pilih kelas</option>
                    @foreach ($classOptions as $class)<option value="{{ $class->id }}">{{ $class->name }} · {{ $class->academicYear->name }}</option>@endforeach
                </select>
                <input name="student_number" required placeholder="NIS/Nomor peserta"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <input name="nisn" inputmode="numeric" maxlength="10" placeholder="NISN 10 digit (opsional)"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <input name="full_name" required placeholder="Nama lengkap siswa"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <input type="email" name="email" placeholder="Email login (opsional)"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <input name="card_uid" placeholder="UID kartu pelajar (opsional)"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <x-password-input name="password" autocomplete="new-password" placeholder="Password awal (kosong = NIS)" aria-label="Password awal (kosong = NIS)"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400" />
                <x-password-input name="password_confirmation" autocomplete="new-password" placeholder="Ulangi password" aria-label="Ulangi password"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400" />
                <p class="sm:col-span-2 text-xs leading-5 text-slate-500">Username memakai NISN jika tersedia, atau NIS. Password yang dikosongkan memakai NIS. Siswa dapat langsung login tanpa wajib mengganti password.</p>
                <button class="sm:col-span-2 rounded-xl bg-emerald-400 px-4 py-3 text-sm font-semibold text-slate-950">Tambah siswa</button>
            </form>
        </section>
    </div>

    <section class="mt-6 rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.045] p-6">
        <div class="grid gap-6 xl:grid-cols-[1fr_420px]">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">05 · Excel</p>
                <h2 class="mt-2 text-xl font-semibold text-white">Impor dan ekspor siswa</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-400">
                    Gunakan template atau file ekspor untuk impor siswa. Isi Kelas dan Tahun Ajaran di Excel agar sistem membaca kelas setiap siswa secara otomatis. NIS wajib. NISN dan email opsional.
                    Akun login otomatis dibuat. Username memakai NISN jika tersedia, atau NIS. Pada siswa baru, Password kosong memakai NIS.
                    Kolom Password boleh diisi untuk menentukan password awal lain.
                </p>
                <form method="POST" action="{{ route('academic.students.participant-cards') }}" target="_blank"
                    class="mt-5 grid max-w-3xl gap-3 rounded-2xl border border-amber-400/20 bg-amber-400/[0.04] p-4 sm:grid-cols-[1fr_1fr_auto]">
                    @csrf
                    <x-assessment-period-select :periods="$assessmentPeriods" :selected="$roomPeriod?->id" :required="true" label="Periode kartu peserta" />
                    <label class="block text-xs font-medium text-amber-200">Kelas yang dicetak
                        <select name="school_class_id" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                            <option value="all">Seluruh kelas</option>
                            @foreach ($classOptions as $class)
                                <option value="{{ $class->id }}">{{ $class->name }} · {{ $class->academicYear->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="self-end rounded-xl bg-amber-400 px-4 py-3 text-sm font-semibold text-slate-950">Cetak kartu</button>
                    <p class="sm:col-span-3 text-xs leading-5 text-amber-100/70">Pilih periode, lalu seluruh kelas atau satu kelas pada tahun ajaran periode tersebut. Setiap kelas dimulai pada halaman baru. Kartu memuat periode, username, password, dan ruangan siswa pada periode terpilih.</p>
                    <p class="sm:col-span-3 text-xs leading-5 text-amber-100/70">Jika password khusus lama belum tersedia untuk cetak, kartu memberi keterangan untuk menghubungi panitia.</p>
                </form>

                <form method="GET" action="{{ route('academic.students.export') }}" class="mt-5 grid max-w-3xl gap-3">
                    <x-assessment-period-select :periods="$assessmentPeriods"
                        :selected="$roomPeriod?->id ?? ($assessmentPeriods->count() === 1 ? $assessmentPeriods->first()->id : null)"
                        label="Periode ruangan untuk ekspor" empty-label="Semua tahun ajaran (Ruangan kosong)" />
                    <p class="text-xs leading-5 text-slate-400">Pilih periode untuk mengunduh seluruh siswa pada tahun ajaran periode tersebut beserta ruangannya. Untuk mengunduh siswa dari semua tahun ajaran, kolom Ruangan tersedia dan dibiarkan kosong.</p>
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="rounded-xl border border-cyan-400/30 px-4 py-2.5 text-sm font-medium text-cyan-200 hover:bg-cyan-400/10">Ekspor seluruh siswa (Excel)</button>
                        <a href="{{ route('academic.students.template') }}" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-medium text-slate-200 hover:bg-white/5">Unduh template Excel</a>
                    </div>
                </form>
            </div>

            <form method="POST" action="{{ route('academic.students.import') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <x-assessment-period-select :periods="$assessmentPeriods" :selected="old('assessment_period_id', $roomPeriod?->id)" label="Periode ruangan untuk impor" />
                <select name="school_class_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                    <option value="">Otomatis dari kolom Kelas & Tahun Ajaran</option>
                    @foreach ($classOptions as $class)<option value="{{ $class->id }}">{{ $class->name }} · {{ $class->academicYear->name }}</option>@endforeach
                </select>
                <input type="file" name="spreadsheet" accept=".xlsx,.csv" required
                    class="block w-full rounded-xl border border-dashed border-white/15 bg-slate-950/50 px-4 py-3 text-sm text-slate-400 file:mr-3 file:rounded-lg file:border-0 file:bg-cyan-400 file:px-3 file:py-2 file:font-semibold file:text-slate-950">
                <button class="w-full rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950">Impor data siswa</button>
                <p class="text-xs leading-5 text-slate-400">Pilih periode saat mengimpor isi kolom Ruangan atau file dengan Kode Periode. Pada file ekspor berperiode, Ruangan yang dikosongkan menghapus penempatan siswa pada periode tersebut. Saat memakai template, pilih periode untuk menerapkan penempatan, termasuk pengosongan ruangan.</p>
                <p class="text-xs text-slate-500">Format XLSX atau CSV. Maksimal 10 MB dan 2.000 siswa sekali impor. Biarkan pilihan kelas pada mode otomatis untuk membaca kelas dari file ekspor.</p>
                <p class="text-xs leading-5 text-cyan-200/80">Impor siswa baru dapat memerlukan beberapa menit karena sistem membuat dan mengenkripsi password setiap akun. Jangan menutup atau memuat ulang halaman selama proses berjalan.</p>
                <p class="text-xs leading-5 text-amber-200/80">Password tidak ikut diekspor. Saat impor ulang, password lama tetap dipertahankan jika kolom Password kosong.</p>
            </form>
        </div>
    </section>

    <section id="student-list" class="scroll-mt-5 mt-6 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
        <div class="border-b border-white/10 p-5 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-white">Daftar siswa</h2>
                    <p class="mt-1 text-sm text-slate-500">Cari berdasarkan nama, NIS, NISN, username, email, atau ruangan.</p>
                </div>
                <span class="rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-200">
                    {{ $students->total() }} siswa
                </span>
            </div>

            <form method="GET" action="{{ route('academic.index') }}#student-list" class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_minmax(200px,280px)_150px_auto]">
                <input type="hidden" name="class_year" value="{{ $classYearId }}">
                <input type="hidden" name="class_per_page" value="{{ $classPerPage }}">
                <x-assessment-period-select :periods="$assessmentPeriods" :selected="$roomPeriod?->id" name="room_period_id" label="Periode pengaturan ruangan" class="sm:col-span-2 xl:col-span-4" />
                <label class="text-xs font-medium text-slate-400">
                    Pencarian siswa
                    <input name="student_search" value="{{ $studentSearch }}" placeholder="Nama, NIS, NISN, ruangan..."
                        class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400">
                </label>
                <label class="text-xs font-medium text-slate-400">
                    Kelas
                    <select name="student_class" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                        <option value="">Semua kelas</option>
                        @foreach ($classOptions as $class)
                            <option value="{{ $class->id }}" @selected($studentClassId === $class->id)>{{ $class->name }} · {{ $class->academicYear->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-400">
                    Data per halaman
                    <select name="student_per_page" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                        @foreach (\App\Support\TablePagination::OPTIONS as $value => $label)
                            <option value="{{ $value }}" @selected($studentPerPage === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-1">
                    <button class="flex-1 rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950">Tampilkan</button>
                    <a href="{{ route('academic.index', ['class_year' => $classYearId, 'class_per_page' => $classPerPage, 'room_period_id' => $roomPeriod?->id]) }}#student-list"
                        class="rounded-xl border border-white/10 px-4 py-3 text-sm font-semibold text-slate-300 hover:bg-white/5">Reset</a>
                </div>
            </form>

            <form id="student-bulk-delete" method="POST" action="{{ route('academic.students.bulk-destroy', request()->query()) }}"
                class="mt-5 flex flex-wrap items-center gap-3 rounded-2xl border border-rose-400/25 bg-rose-400/[0.04] p-4">
                @csrf @method('DELETE')
                <input type="hidden" name="roster_revision" value="{{ $rosterRevision }}">
                <div data-selected-students></div>
                <button type="submit" data-bulk-delete-submit disabled class="rounded-xl border border-rose-400/40 bg-rose-400/10 px-4 py-3 text-sm font-semibold text-rose-300 disabled:cursor-not-allowed disabled:opacity-50">Hapus siswa terpilih</button>
                <span data-student-selection-count class="text-sm text-slate-400" aria-live="polite">0 siswa terpilih.</span>
                <p class="w-full text-xs leading-6 text-slate-400">Centang siswa yang ingin dihapus. Pilih semua hanya mencentang data pada halaman ini; pilih Semua pada jumlah data untuk melihat seluruh hasil filter. Maksimal 500 siswa sekali hapus. Akun login, ruangan, absensi, nilai, serta riwayat ujian siswa terpilih ikut dihapus permanen.</p>
                <p data-student-selection-warning hidden class="w-full text-xs text-amber-300" role="status">Pilih maksimal 500 siswa sekaligus, atau gunakan filter kelas dan jumlah data per halaman.</p>
            </form>

            @if ($rosterTotals->student_count > 0)
                <details class="mt-4 rounded-2xl border border-rose-400/30 p-4" @if ($errors->has('confirmation') || $errors->has('expected_count') || $errors->has('expected_last_id')) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold text-rose-300">Hapus semua siswa ({{ $rosterTotals->student_count }})</summary>
                    <form method="POST" action="{{ route('academic.students.destroy-all', request()->query()) }}" class="mt-3 space-y-3">
                        @csrf @method('DELETE')
                        <input type="hidden" name="roster_revision" value="{{ $rosterRevision }}">
                        <input type="hidden" name="expected_count" value="{{ $rosterTotals->student_count }}">
                        <input type="hidden" name="expected_last_id" value="{{ $rosterTotals->last_id }}">
                        <p class="text-sm text-slate-300">Menghapus permanen {{ $rosterTotals->student_count }} siswa dari seluruh kelas dan tahun ajaran, termasuk akun, nilai, absensi, dan riwayat ujian. Pilihan filter tabel tidak berlaku.</p>
                        <div class="flex flex-wrap items-end gap-3">
                            <label for="confirm-delete-all-students" class="text-xs text-slate-300">Ketik HAPUS SEMUA untuk konfirmasi
                                <input id="confirm-delete-all-students" name="confirmation" required maxlength="11" autocomplete="off" class="mt-2 block rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm">
                            </label>
                            <button type="submit" class="rounded-xl border border-rose-400/40 bg-rose-400/10 px-4 py-2.5 text-sm font-semibold text-rose-300">Hapus semua siswa permanen</button>
                        </div>
                    </form>
                </details>
            @endif

            @if ($roomPeriod)
            <p class="mt-4 text-sm font-medium text-emerald-300">Ruangan untuk {{ $roomPeriod->name }} · {{ $roomPeriod->academicYear->name }}</p>
            <form id="student-room-bulk" method="POST" action="{{ route('academic.students.rooms') }}"
                class="mt-5 grid gap-3 rounded-2xl border border-emerald-400/20 bg-emerald-400/[0.04] p-4 sm:grid-cols-[1fr_auto]">
                @csrf @method('PATCH')
                <input type="hidden" name="roster_revision" value="{{ $rosterRevision }}">
                <input type="hidden" name="assessment_period_id" value="{{ $roomPeriod->id }}">
                <label class="text-xs font-medium text-slate-400">Atur ruangan siswa terpilih
                    <input name="bulk_room_name" maxlength="80" list="student-room-options" value="{{ old('bulk_room_name') }}" placeholder="Contoh Ruang 01 atau Lab Komputer"
                        class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                </label>
                <button class="self-end rounded-xl bg-emerald-400 px-4 py-3 text-sm font-semibold text-slate-950 disabled:opacity-50">Simpan ruangan siswa terpilih</button>
                <p class="sm:col-span-2 text-xs leading-5 text-slate-400"><span data-room-selection-count>0 siswa terpilih.</span> Centang siswa pada tabel. Pilih semua hanya mencentang halaman ini. Gunakan filter kelas dan pilihan Semua data untuk mengatur satu kelas sekaligus. Maksimal 500 siswa sekali simpan. Kosongkan nama ruangan untuk menghapus penempatannya.</p>
            </form>
            <datalist id="student-room-options">
                @foreach ($roomOptions as $room)<option value="{{ $room }}"></option>@endforeach
            </datalist>
            @else
                <p class="mt-4 text-sm text-amber-200">Pilih periode ujian lalu klik Tampilkan untuk melihat dan mengatur ruangan siswa pada periode tersebut.</p>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="data-grid responsive-data-table min-w-full text-left text-sm">
                <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-slate-500">
                    <tr><th class="px-5 py-4"><input type="checkbox" data-room-select-all aria-label="Pilih semua siswa pada halaman ini"></th><th class="px-5 py-4">No.</th><th class="px-5 py-4">NIS</th><th class="px-5 py-4">NISN</th><th class="px-5 py-4">Nama</th><th class="px-5 py-4">Kelas</th><th class="px-5 py-4">Ruangan</th><th class="px-5 py-4">Login</th><th class="px-5 py-4 text-right">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($students as $student)
                        <tr>
                            <td data-label="Pilih" class="px-5 py-4"><input type="checkbox" name="student_ids[]" value="{{ $student->id }}" form="student-room-bulk" data-room-student aria-label="Pilih {{ $student->full_name }}"></td>
                            <td data-label="No." data-student-row-number class="px-5 py-4 text-slate-400">{{ ($students->firstItem() ?? 1) + $loop->index }}</td>
                            <td data-label="NIS" class="px-5 py-4 text-slate-400">{{ $student->student_number }}</td>
                            <td data-label="NISN" class="px-5 py-4 text-slate-400">{{ $student->nisn ?? '—' }}</td>
                            <td data-label="Nama" class="px-5 py-4 font-medium text-white">{{ $student->full_name }}</td>
                            <td data-label="Kelas" class="px-5 py-4 text-slate-400">{{ $student->schoolClass->name }}</td>
                            <td data-label="Ruangan" class="px-5 py-4">
                                @if ($roomPeriod)
                                <form method="POST" action="{{ route('academic.students.room', $student) }}" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="roster_revision" value="{{ $rosterRevision }}">
                                    <input type="hidden" name="assessment_period_id" value="{{ $roomPeriod->id }}">
                                    <input name="room_name" value="{{ $student->examRooms->first()?->room_name }}" maxlength="80" list="student-room-options" placeholder="Belum diatur" aria-label="Ruangan {{ $student->full_name }}"
                                        class="min-w-32 w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-xs outline-none focus:border-emerald-400">
                                    <button class="text-xs font-semibold text-emerald-300">Simpan</button>
                                </form>
                                @else
                                    <span class="text-xs text-slate-500">Pilih periode ujian</span>
                                @endif
                            </td>
                            <td data-label="Login" class="px-5 py-4 text-slate-400">
                                <span class="block">{{ $student->user?->username ?? 'Belum dibuat' }}</span>
                                @if ($student->user?->email)<span class="mt-1 block text-xs text-slate-600">{{ $student->user->email }}</span>@endif
                            </td>
                            <td data-label="Aksi" class="px-5 py-4">
                                <div class="flex justify-end gap-3">
                                    <form method="POST" action="{{ route('academic.students.toggle', $student) }}">@csrf @method('PATCH')
                                        <input type="hidden" name="roster_revision" value="{{ $rosterRevision }}">
                                        <button class="text-xs {{ $student->is_active ? 'text-emerald-300' : 'text-slate-500' }}">{{ $student->is_active ? 'Aktif' : 'Nonaktif' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('academic.students.destroy', [...request()->query(), 'student' => $student]) }}" onsubmit="return confirm('Hapus permanen siswa ini beserta akun, ruangan, absensi, nilai, dan riwayat ujian terkait?')">@csrf @method('DELETE')
                                        <input type="hidden" name="roster_revision" value="{{ $rosterRevision }}">
                                        <button class="text-xs text-rose-300">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-6 py-12 text-center text-slate-500">Data siswa tidak ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-white/10 p-5">

            <div class="mt-4">{{ $students->links() }}</div>

        </div>

    </section>
@endsection
