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

        <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
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

            <div class="mt-6 space-y-2">
                @forelse ($classes as $class)
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-slate-950/40 p-3">
                        <div>
                            <p class="text-sm font-medium text-white">{{ $class->name }}</p>
                            <p class="text-xs text-slate-500">{{ $class->academicYear->name }} · {{ $class->students_count }} siswa</p>
                        </div>
                        <form method="POST" action="{{ route('academic.classes.destroy', $class) }}" onsubmit="return confirm('Hapus kelas ini?')">@csrf @method('DELETE')
                            <button class="text-xs text-rose-300">Hapus</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada kelas.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">04 · Siswa</p>
            <h2 class="mt-2 text-xl font-semibold text-white">Tambah peserta didik</h2>

            <form method="POST" action="{{ route('academic.students.store') }}" class="mt-6 grid gap-3 sm:grid-cols-2">
                @csrf
                <select name="school_class_id" required class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                    <option value="">Pilih kelas</option>
                    @foreach ($classes as $class)<option value="{{ $class->id }}">{{ $class->name }} · {{ $class->academicYear->name }}</option>@endforeach
                </select>
                <input name="student_number" required placeholder="NIS/Nomor peserta"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <input name="nisn" inputmode="numeric" maxlength="10" placeholder="NISN 10 digit (opsional)"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <input name="full_name" required placeholder="Nama lengkap siswa"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <input type="email" name="email" placeholder="Email login (opsional)"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <input type="password" name="password" placeholder="Password untuk membuat login"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <input type="password" name="password_confirmation" placeholder="Ulangi password"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <p class="sm:col-span-2 text-xs leading-5 text-slate-500">Login siswa memakai NISN jika tersedia, jika tidak memakai NIS. Email tidak wajib.</p>
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
                    Unduh template, isi banyak siswa sekaligus, lalu pilih kelas tujuan. NIS wajib; NISN dan email opsional.
                    Password hanya diisi jika akun login siswa ingin langsung dibuat.
                </p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('academic.students.template') }}" class="rounded-xl border border-cyan-400/30 px-4 py-2.5 text-sm font-medium text-cyan-200 hover:bg-cyan-400/10">Unduh template Excel</a>
                    <a href="{{ route('academic.students.export') }}" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-medium text-slate-200 hover:bg-white/5">Ekspor seluruh siswa</a>
                </div>
            </div>

            <form method="POST" action="{{ route('academic.students.import') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <select name="school_class_id" required class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                    <option value="">Pilih kelas tujuan impor</option>
                    @foreach ($classes as $class)<option value="{{ $class->id }}">{{ $class->name }} · {{ $class->academicYear->name }}</option>@endforeach
                </select>
                <input type="file" name="spreadsheet" accept=".xlsx,.csv" required
                    class="block w-full rounded-xl border border-dashed border-white/15 bg-slate-950/50 px-4 py-3 text-sm text-slate-400 file:mr-3 file:rounded-lg file:border-0 file:bg-cyan-400 file:px-3 file:py-2 file:font-semibold file:text-slate-950">
                <button class="w-full rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950">Impor data siswa</button>
                <p class="text-xs text-slate-500">Format: XLSX/CSV, maksimal 10 MB dan 2.000 siswa sekali impor.</p>
            </form>
        </div>
    </section>

    <section class="mt-6 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
        <div class="border-b border-white/10 p-6">
            <h2 class="text-xl font-semibold text-white">Daftar siswa</h2>
            <p class="mt-1 text-sm text-slate-500">Maksimal 20 data per halaman.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-slate-500">
                    <tr><th class="px-5 py-4">NIS</th><th class="px-5 py-4">NISN</th><th class="px-5 py-4">Nama</th><th class="px-5 py-4">Kelas</th><th class="px-5 py-4">Login</th><th class="px-5 py-4 text-right">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($students as $student)
                        <tr>
                            <td class="px-5 py-4 text-slate-400">{{ $student->student_number }}</td>
                            <td class="px-5 py-4 text-slate-400">{{ $student->nisn ?? '—' }}</td>
                            <td class="px-5 py-4 font-medium text-white">{{ $student->full_name }}</td>
                            <td class="px-5 py-4 text-slate-400">{{ $student->schoolClass->name }}</td>
                            <td class="px-5 py-4 text-slate-400">
                                <span class="block">{{ $student->user?->username ?? 'Belum dibuat' }}</span>
                                @if ($student->user?->email)<span class="mt-1 block text-xs text-slate-600">{{ $student->user->email }}</span>@endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-3">
                                    <form method="POST" action="{{ route('academic.students.toggle', $student) }}">@csrf @method('PATCH')
                                        <button class="text-xs {{ $student->is_active ? 'text-emerald-300' : 'text-slate-500' }}">{{ $student->is_active ? 'Aktif' : 'Nonaktif' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('academic.students.destroy', $student) }}" onsubmit="return confirm('Hapus siswa ini?')">@csrf @method('DELETE')
                                        <button class="text-xs text-rose-300">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">Belum ada siswa.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($students->hasPages())
            <div class="border-t border-white/10 p-5">{{ $students->links() }}</div>
        @endif
    </section>
@endsection
