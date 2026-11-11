@extends('layouts.app')

@section('title', 'Penjadwalan')
@section('eyebrow', 'Operasional Asesmen')
@section('heading', 'Penjadwalan Ujian')

@section('content')
    @php
        $typeLabels = ['ats' => 'ATS', 'aas' => 'AAS', 'aat' => 'AAT', 'uub' => 'UUB', 'other' => 'Lainnya'];
        $semesterLabels = ['odd' => 'Ganjil', 'even' => 'Genap'];
        $statusLabels = ['draft' => 'Draf', 'published' => 'Terbit', 'active' => 'Aktif', 'closed' => 'Selesai'];
    @endphp

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mb-6 rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.06] p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Alur aman</p>
        <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-300">
            Buat lokasi, periode, dan mapel-kelas. Atur satu jadwal untuk semua rombel setingkat pada mapel yang sama. Sesi reguler otomatis menempatkan siswa aktif yang belum memiliki penugasan.
            Saat susulan, data yang sama dipindahkan ke sesi susulan sehingga nilai dan riwayat tidak menjadi ganda.
        </p>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        @include('campuses.partials.manager')

        <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">02 · Periode</p>
            <h2 class="mt-2 text-xl font-semibold text-white">ATS, AAS/AAT, atau UUB</h2>

            <form method="POST" action="{{ route('scheduling.periods.store') }}" class="mt-6 grid gap-3 sm:grid-cols-2">
                @csrf
                <select name="academic_year_id" required class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                    <option value="">Tahun ajaran</option>
                    @foreach ($academicYears as $year)<option value="{{ $year->id }}">{{ $year->name }}</option>@endforeach
                </select>
                <input name="code" required placeholder="Kode, contoh ATS-GANJIL"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm uppercase outline-none focus:border-violet-400">
                <input name="name" required placeholder="Nama periode"
                    class="sm:col-span-2 rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-violet-400">
                <select name="type" required class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                    @foreach ($assessmentTypes as $type)<option value="{{ $type->value }}">{{ $typeLabels[$type->value] }}</option>@endforeach
                </select>
                <select name="semester" required class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                    @foreach ($semesters as $semester)<option value="{{ $semester->value }}">{{ $semesterLabels[$semester->value] }}</option>@endforeach
                </select>
                <input type="number" name="sequence_no" min="1" max="20" placeholder="Urutan UUB (opsional)"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-violet-400">
                <span></span>
                <input type="date" name="starts_on" required class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                <input type="date" name="ends_on" required class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                <button class="sm:col-span-2 rounded-xl bg-violet-400 px-4 py-3 text-sm font-semibold text-slate-950">Buat periode</button>
            </form>
        </section>
    </div>

    <section class="mt-6 rounded-3xl border border-white/10 bg-white/[0.035] p-6">
        <div class="grid gap-6 xl:grid-cols-[1fr_420px]">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-300">03 · Daftar Periode</p>
                <div class="mt-4 space-y-3">
                    @forelse ($periods as $period)
                        <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full bg-amber-400/10 px-2.5 py-1 text-xs text-amber-200">{{ $typeLabels[$period->type->value] }}</span>
                                        <span class="text-xs text-slate-500">{{ $semesterLabels[$period->semester->value] }}</span>
                                    </div>
                                    <p class="mt-2 font-medium text-white">{{ $period->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $period->academicYear->name }} · {{ $period->code }} · {{ $period->assessment_subjects_count }} komponen</p>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <form method="POST" action="{{ route('scheduling.periods.status', $period) }}" class="flex items-center gap-2">
                                        @csrf @method('PATCH')
                                        <select name="status" class="rounded-lg border border-white/10 bg-slate-950 px-2 py-1.5 text-xs">
                                            @foreach ($periodStatuses as $status)
                                                <option value="{{ $status->value }}" @selected($period->status === $status)>{{ $statusLabels[$status->value] }}</option>
                                            @endforeach
                                        </select>
                                        <button class="text-xs text-cyan-300">Simpan</button>
                                    </form>
                                    @if ($period->assessment_subjects_count === 0)
                                        <form method="POST" action="{{ route('scheduling.periods.destroy', $period) }}" onsubmit="return confirm('Hapus periode kosong ini?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-rose-300">Hapus periode</button>
                                        </form>
                                    @else
                                        <span class="max-w-56 text-right text-[11px] leading-4 text-slate-500">Sudah digunakan—pilih status Selesai untuk mengarsipkan dan menjaga jawaban serta nilai peserta.</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada periode asesmen.</p>
                    @endforelse
                </div>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-300">04 · Mapel & Kelas</p>
                <form method="POST" action="{{ route('scheduling.components.store') }}" class="mt-4 space-y-3">
                    @csrf
                    <select name="assessment_period_id" required class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                        <option value="">Pilih periode</option>
                        @foreach ($periods as $period)<option value="{{ $period->id }}">{{ $period->name }} · {{ $period->academicYear->name }}</option>@endforeach
                    </select>
                    <select name="subject_id" required class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                        <option value="">Pilih mata pelajaran</option>
                        @foreach ($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->code }} · {{ $subject->name }}</option>@endforeach
                    </select>
                    <select name="school_class_id" required class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                        <option value="">Pilih kelas</option>
                        @foreach ($classes as $class)<option value="{{ $class->id }}">{{ $class->name }} · {{ $class->academicYear->name }}</option>@endforeach
                    </select>
                    <select name="teacher_user_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                        <option value="">Guru pengoreksi (dapat diatur nanti)</option>
                        @foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }} · {{ $teacher->email }}</option>@endforeach
                    </select>
                    <button class="w-full rounded-xl bg-amber-400 px-4 py-3 text-sm font-semibold text-slate-950">Tambahkan ke periode</button>
                </form>
            </div>
        </div>
    </section>

    <section class="mt-6">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">05 · Sesi Ujian</p>
                <h2 class="mt-2 text-xl font-semibold text-white">Jadwal reguler dan susulan</h2>
                <p class="mt-1 text-xs text-slate-500">Jadwal dikelompokkan per mapel dan tingkat, maksimal 12 kelompok per halaman. Buka pengaturan untuk mengatur seluruh rombel sekaligus.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('scheduling.index') }}" class="mb-5 grid gap-3 rounded-2xl border border-white/10 bg-white/[0.025] p-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_auto_auto]">
            <select name="session_period_id" class="rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm">
                <option value="">Semua periode</option>
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}" @selected($sessionPeriodId === $period->id)>{{ $period->name }} · {{ $period->academicYear->name }}</option>
                @endforeach
            </select>
            <select name="session_class_id" class="rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm">
                <option value="">Semua kelompok rombel</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected($sessionClassId === $class->id)>{{ $class->name }} · {{ $class->academicYear->name }}</option>
                @endforeach
            </select>
            <input name="session_search" value="{{ $sessionSearch }}" placeholder="Cari mapel atau kode"
                class="rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm outline-none focus:border-cyan-400">
            <button class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Tampilkan</button>
            <a href="{{ route('scheduling.index') }}#sesi-ujian" class="rounded-xl border border-white/10 px-4 py-2.5 text-center text-sm text-slate-300">Reset</a>
        </form>

        <div id="sesi-ujian" class="space-y-4">
            @forelse ($components as $component)
                @include('scheduling.partials.grade-schedule', ['group' => $component])
            @empty
                <div class="rounded-3xl border border-dashed border-white/10 p-10 text-center text-sm text-slate-500">
                    Tambahkan periode beserta mapel dan kelas sebelum membuat sesi.
                </div>
            @endforelse
        </div>
        @if ($components->hasPages())
            <div class="mt-5 rounded-2xl border border-white/10 bg-white/[0.025] p-4">
                {{ $components->links() }}
            </div>
        @endif
    </section>

    <section id="peserta-susulan" class="mt-6 rounded-3xl border border-amber-400/20 bg-amber-400/[0.05] p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-300">06 · Peserta Susulan</p>
        <h2 class="mt-2 text-xl font-semibold text-white">Pindahkan tanpa duplikasi data</h2>
        <form method="POST" action="{{ route('scheduling.makeup.move') }}" class="mt-5 grid gap-3 lg:grid-cols-[1fr_1fr_auto]">
            @csrf
            <select name="assignment_id" required class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                <option value="">Pilih peserta yang belum selesai</option>
                @foreach ($movableAssignments as $assignment)
                    <option value="{{ $assignment->id }}">{{ $assignment->student->full_name }} · {{ $assignment->assessmentSubject->subject->name }} · {{ $assignment->student->schoolClass->name }}</option>
                @endforeach
            </select>
            <select name="makeup_session_id" required class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                <option value="">Pilih sesi susulan yang sesuai</option>
                @foreach ($makeupSessions as $session)
                    <option value="{{ $session->id }}">{{ $session->assessmentSubject->subject->name }} · {{ $session->assessmentSubject->schoolClass->name }} · {{ $session->starts_at->format('d/m/Y H:i') }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-amber-400 px-5 py-3 text-sm font-semibold text-slate-950">Pindahkan ke susulan</button>
        </form>
    </section>
@endsection
