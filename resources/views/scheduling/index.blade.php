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
            Buat lokasi, periode, mapel-kelas, lalu sesi reguler. Tombol “Tempatkan siswa” memakai satu penugasan per siswa dan komponen asesmen.
            Saat susulan, data yang sama dipindahkan ke sesi susulan sehingga nilai dan riwayat tidak menjadi ganda.
        </p>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">01 · Lokasi</p>
            <h2 class="mt-2 text-xl font-semibold text-white">Lokasi dan radius ujian</h2>

            <form method="POST" action="{{ route('scheduling.campuses.store') }}" class="mt-6 grid gap-3 sm:grid-cols-2">
                @csrf
                <input name="name" required placeholder="Nama lokasi/kampus"
                    class="sm:col-span-2 rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <input type="number" step="0.0000001" name="latitude" required placeholder="Latitude"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <input type="number" step="0.0000001" name="longitude" required placeholder="Longitude"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <input type="number" name="radius_meters" value="100" min="10" max="5000" required placeholder="Radius (meter)"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <input type="number" name="max_accuracy_meters" value="50" min="5" max="500" required placeholder="Akurasi GPS maksimum"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
                <button class="sm:col-span-2 rounded-xl bg-emerald-400 px-4 py-3 text-sm font-semibold text-slate-950">Simpan lokasi</button>
            </form>

            <div class="mt-5 space-y-2">
                @forelse ($campuses as $campus)
                    <div class="flex items-center justify-between rounded-xl border border-white/10 bg-slate-950/40 p-3">
                        <div>
                            <p class="text-sm font-medium text-white">{{ $campus->name }}</p>
                            <p class="text-xs text-slate-500">Radius {{ $campus->radius_meters }} m · akurasi ≤ {{ $campus->max_accuracy_meters }} m</p>
                        </div>
                        <form method="POST" action="{{ route('scheduling.campuses.toggle', $campus) }}">@csrf @method('PATCH')
                            <button class="text-xs {{ $campus->is_active ? 'text-emerald-300' : 'text-slate-500' }}">{{ $campus->is_active ? 'Aktif' : 'Nonaktif' }}</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada lokasi ujian.</p>
                @endforelse
            </div>
        </section>

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
                                <form method="POST" action="{{ route('scheduling.periods.status', $period) }}" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <select name="status" class="rounded-lg border border-white/10 bg-slate-950 px-2 py-1.5 text-xs">
                                        @foreach ($periodStatuses as $status)
                                            <option value="{{ $status->value }}" @selected($period->status === $status)>{{ $statusLabels[$status->value] }}</option>
                                        @endforeach
                                    </select>
                                    <button class="text-xs text-cyan-300">Simpan</button>
                                </form>
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
                    <button class="w-full rounded-xl bg-amber-400 px-4 py-3 text-sm font-semibold text-slate-950">Tambahkan ke periode</button>
                </form>
            </div>
        </div>
    </section>

    <section class="mt-6">
        <div class="mb-4">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">05 · Sesi Ujian</p>
            <h2 class="mt-2 text-xl font-semibold text-white">Jadwal reguler dan susulan</h2>
        </div>

        <div class="space-y-5">
            @forelse ($components as $component)
                @php($componentLocked = $component->examSessions->isNotEmpty() || $component->assignments_count > 0 || $component->questions_count > 0)
                <article class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-white">{{ $component->subject->name }} · {{ $component->schoolClass->name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $component->assessmentPeriod->name }} · {{ $component->assessmentPeriod->academicYear->name }}</p>
                            <a href="{{ route('scheduling.questions.index', $component) }}" class="mt-2 inline-flex text-xs font-medium text-violet-300 hover:text-violet-200">Kelola bank soal →</a>
                        </div>
                        <form method="POST" action="{{ route('scheduling.components.destroy', $component) }}" onsubmit="return confirm('Hapus mapel dan kelas dari periode ini?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-rose-300">Hapus komponen</button>
                        </form>
                    </div>

                    <details class="mt-4 rounded-2xl border border-white/10 bg-slate-950/35 p-4">
                        <summary class="cursor-pointer text-sm font-medium text-amber-300">Edit periode, mapel, atau kelas</summary>
                        @if ($componentLocked)
                            <p class="mt-3 text-xs leading-5 text-slate-500">Identitas komponen dikunci karena sudah memiliki sesi, peserta, atau soal. Jika salah mapel atau kelas, buat komponen baru yang sesuai dan pertahankan riwayat ujian lama.</p>
                        @else
                            <form method="POST" action="{{ route('scheduling.components.update', $component) }}" class="mt-4 grid gap-3 md:grid-cols-3">
                                @csrf @method('PATCH')
                                <select name="assessment_period_id" required class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                                    @foreach ($periods as $period)
                                        <option value="{{ $period->id }}" @selected($component->assessment_period_id === $period->id)>{{ $period->name }} · {{ $period->academicYear->name }}</option>
                                    @endforeach
                                </select>
                                <select name="subject_id" required class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                                    @foreach ($subjects as $subject)
                                        <option value="{{ $subject->id }}" @selected($component->subject_id === $subject->id)>{{ $subject->code }} · {{ $subject->name }}</option>
                                    @endforeach
                                </select>
                                <select name="school_class_id" required class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                                    @foreach ($classes as $class)
                                        <option value="{{ $class->id }}" @selected($component->school_class_id === $class->id)>{{ $class->name }} · {{ $class->academicYear->name }}</option>
                                    @endforeach
                                </select>
                                <button class="rounded-xl bg-amber-400 px-4 py-2.5 text-sm font-semibold text-slate-950 md:col-span-3">Simpan perubahan komponen</button>
                            </form>
                        @endif
                    </details>

                    <details class="mt-5 rounded-2xl border border-white/10 bg-slate-950/35 p-4">
                        <summary class="cursor-pointer text-sm font-medium text-cyan-300">Tambah sesi untuk komponen ini</summary>
                        <form method="POST" action="{{ route('scheduling.sessions.store') }}" class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            @csrf
                            <input type="hidden" name="assessment_subject_id" value="{{ $component->id }}">
                            <select name="campus_id" required class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                                <option value="">Lokasi</option>
                                @foreach ($campuses->where('is_active', true) as $campus)<option value="{{ $campus->id }}">{{ $campus->name }}</option>@endforeach
                            </select>
                            <select name="kind" required class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                                <option value="regular">Reguler</option>
                                <option value="makeup">Susulan</option>
                            </select>
                            <select name="source_session_id" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                                <option value="">Sumber reguler (wajib untuk susulan)</option>
                                @foreach ($component->examSessions->where('kind.value', 'regular') as $source)
                                    <option value="{{ $source->id }}">{{ $source->starts_at->format('d/m/Y H:i') }}</option>
                                @endforeach
                            </select>
                            <select name="status" required class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                                @foreach ($sessionStatuses as $status)<option value="{{ $status->value }}">{{ $statusLabels[$status->value] }}</option>@endforeach
                            </select>
                            <input type="datetime-local" name="starts_at" required class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                            <input type="number" name="duration_minutes" value="90" min="10" max="600" required class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                            <input name="room_name" placeholder="Ruangan (opsional)" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                            <button class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Simpan sesi</button>
                            <p class="text-xs leading-5 text-slate-500 md:col-span-2 xl:col-span-4">Jam selesai dihitung otomatis dari jam mulai dan durasi. Sistem menolak bentrok kelas maupun ruangan.</p>
                        </form>
                    </details>

                    <div class="mt-4 grid gap-3 lg:grid-cols-2">
                        @forelse ($component->examSessions as $session)
                            <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs {{ $session->kind->value === 'makeup' ? 'bg-amber-400/10 text-amber-200' : 'bg-cyan-400/10 text-cyan-200' }}">
                                        {{ $session->kind->value === 'makeup' ? 'Susulan' : 'Reguler' }}
                                    </span>
                                    <span class="text-xs text-slate-500">{{ $statusLabels[$session->status->value] }}</span>
                                </div>
                                <p class="mt-3 text-sm font-medium text-white">{{ $session->starts_at->format('d/m/Y H:i') }}–{{ $session->ends_at->format('H:i') }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $session->campus->name }}{{ $session->room_name ? ' · '.$session->room_name : '' }} · {{ $session->duration_minutes }} menit · {{ $session->assignments_count }} peserta</p>
                                <details class="mt-3 rounded-xl border border-white/10 p-3">
                                    <summary class="cursor-pointer text-xs font-medium text-cyan-300">Edit sesi ujian</summary>
                                    <p class="mt-2 text-xs text-slate-400">Jadwal dan durasi hanya dapat diubah sebelum siswa mulai. Menutup sesi akan mengumpulkan jawaban; sesi tertutup tidak dapat dibuka ulang.</p>
                                    <form method="POST" action="{{ route('scheduling.sessions.update', $session) }}" class="mt-3 grid gap-2 sm:grid-cols-2">
                                        @csrf @method('PATCH')
                                        <select name="campus_id" required class="rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-xs">
                                            @foreach ($campuses->where('is_active', true) as $campus)
                                                <option value="{{ $campus->id }}" @selected($session->campus_id === $campus->id)>{{ $campus->name }}</option>
                                            @endforeach
                                        </select>
                                        <select name="kind" required class="rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-xs">
                                            <option value="regular" @selected($session->kind->value === 'regular')>Reguler</option>
                                            <option value="makeup" @selected($session->kind->value === 'makeup')>Susulan</option>
                                        </select>
                                        <select name="source_session_id" class="rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-xs">
                                            <option value="">Sumber reguler</option>
                                            @foreach ($component->examSessions->where('kind.value', 'regular')->where('id', '!=', $session->id) as $source)
                                                <option value="{{ $source->id }}" @selected($session->source_session_id === $source->id)>{{ $source->starts_at->format('d/m/Y H:i') }}</option>
                                            @endforeach
                                        </select>
                                        <select name="status" required class="rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-xs">
                                            @foreach ($sessionStatuses as $status)
                                                <option value="{{ $status->value }}" @selected($session->status === $status)>{{ $statusLabels[$status->value] }}</option>
                                            @endforeach
                                        </select>
                                        <input type="datetime-local" name="starts_at" value="{{ $session->starts_at->format('Y-m-d\TH:i') }}" required class="rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-xs">
                                        <input type="number" name="duration_minutes" value="{{ $session->duration_minutes }}" min="10" max="600" required class="rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-xs">
                                        <input name="room_name" value="{{ $session->room_name }}" placeholder="Ruangan (opsional)" class="rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-xs sm:col-span-2">
                                        <button class="rounded-lg bg-cyan-400 px-4 py-2.5 text-xs font-semibold text-slate-950 sm:col-span-2">Simpan perubahan sesi</button>
                                    </form>
                                </details>
                                <div class="mt-3 flex items-center justify-between">
                                    @if ($session->kind->value === 'regular')
                                        <form method="POST" action="{{ route('scheduling.assign-class', [$component, $session]) }}">@csrf
                                            <button class="text-xs text-emerald-300">Tempatkan siswa aktif</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-amber-300">Tujuan susulan</span>
                                    @endif
                                    <form method="POST" action="{{ route('scheduling.sessions.destroy', $session) }}" onsubmit="return confirm('Hapus sesi ini?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-rose-300">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Belum ada sesi untuk komponen ini.</p>
                        @endforelse
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-white/10 p-10 text-center text-sm text-slate-500">
                    Tambahkan periode beserta mapel dan kelas sebelum membuat sesi.
                </div>
            @endforelse
        </div>
    </section>

    <section class="mt-6 rounded-3xl border border-amber-400/20 bg-amber-400/[0.05] p-6">
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
