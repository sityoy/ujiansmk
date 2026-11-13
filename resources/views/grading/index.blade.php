@extends('layouts.app')

@section('title', 'Koreksi Jawaban')
@section('eyebrow', 'Penilaian Manual')
@section('heading', 'Koreksi Isian dan Esai')

@section('content')
    <section class="rounded-3xl border border-violet-400/20 bg-violet-400/[0.045] p-6">
        <h2 class="text-xl font-semibold text-white">Jawaban yang sudah dikumpulkan</h2>
        <p class="mt-2 text-sm leading-6 text-slate-400">Menampilkan jawaban manual dari ATS, AAS, AAT, UUB, dan periode lainnya. Guru hanya melihat mapel/kelas yang ditugaskan kepadanya.</p>
    </section>
    <form method="GET" class="mt-6 grid gap-3 rounded-2xl border border-white/10 bg-slate-950/40 p-4 md:grid-cols-2 xl:grid-cols-6">
        <select name="academic_year_id" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
            <option value="">Semua tahun pelajaran</option>
            @foreach ($academicYears as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? '') == $year->id)>{{ $year->name }}</option>@endforeach
        </select>
        <select name="assessment_period_id" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
            <option value="">Semua periode</option>
            @foreach ($periodOptions as $period)<option value="{{ $period->id }}" @selected(($filters['assessment_period_id'] ?? '') == $period->id)>{{ $period->name }} · {{ $period->academicYear->name }}</option>@endforeach
        </select>
        <select name="type" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
            <option value="">Semua jenis</option>
            @foreach ($assessmentTypes as $type)<option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ strtoupper($type->value) }}</option>@endforeach
        </select>
        <select name="school_class_id" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
            <option value="">Semua kelas</option>
            @foreach ($classOptions as $class)<option value="{{ $class->id }}" @selected(($filters['school_class_id'] ?? '') == $class->id)>{{ $class->name }} · {{ $class->academicYear->name }}</option>@endforeach
        </select>
        <select name="grading_status" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
            <option value="">Semua status koreksi</option>
            <option value="pending" @selected(($filters['grading_status'] ?? '') === 'pending')>Menunggu koreksi</option>
            <option value="graded" @selected(($filters['grading_status'] ?? '') === 'graded')>Selesai dikoreksi</option>
        </select>
        <div class="flex items-center gap-3"><button class="rounded-xl bg-violet-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Tampilkan</button><a href="{{ route('grading.index') }}" class="text-sm text-slate-400">Reset</a></div>
    </form>

    <section class="mt-6 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-950/60 text-xs uppercase text-slate-500"><tr><th class="px-5 py-4">Peserta</th><th class="px-5 py-4">Periode</th><th class="px-5 py-4">Mapel/Kelas</th><th class="px-5 py-4">Guru</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Aksi</th></tr></thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($attempts as $attempt)
                        <tr>
                            <td class="px-5 py-4"><p class="font-medium text-white">{{ $attempt->assignment->student->full_name }}</p><p class="mt-1 text-xs text-slate-500">NIS {{ $attempt->assignment->student->student_number }}</p></td>
                            <td class="px-5 py-4 text-slate-400"><p>{{ $attempt->assignment->assessmentSubject->assessmentPeriod->name }}</p><p class="mt-1 text-xs text-slate-600">{{ strtoupper($attempt->assignment->assessmentSubject->assessmentPeriod->type->value) }} · {{ $attempt->assignment->assessmentSubject->assessmentPeriod->academicYear->name }}</p></td>
                            <td class="px-5 py-4 text-slate-300">{{ $attempt->assignment->assessmentSubject->subject->name }} · {{ $attempt->assignment->assessmentSubject->schoolClass->name }}</td>
                            <td class="px-5 py-4 text-slate-400">{{ $attempt->assignment->assessmentSubject->teacher?->name ?? 'Belum ditentukan' }}</td>
                            <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs {{ $attempt->grading_status->value === 'graded' ? 'bg-emerald-400/10 text-emerald-200' : 'bg-amber-400/10 text-amber-200' }}">{{ $attempt->grading_status->label() }}</span><p class="mt-2 text-xs text-slate-500">Nilai: {{ $attempt->score ?? '—' }}</p></td>
                            <td class="px-5 py-4"><a href="{{ route('grading.show', $attempt) }}" class="rounded-lg border border-violet-400/30 px-3 py-2 text-xs font-semibold text-violet-200">{{ $attempt->grading_status->value === 'graded' ? 'Periksa ulang' : 'Mulai koreksi' }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">Belum ada jawaban isian atau esai yang menunggu koreksi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($attempts->hasPages())<div class="p-5">{{ $attempts->links() }}</div>@endif
    </section>
@endsection
