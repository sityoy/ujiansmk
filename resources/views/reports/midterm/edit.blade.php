@extends('layouts.app')

@section('title', 'Kelola Rapor ATS '.$schoolClass->name)
@section('eyebrow', 'Entri Rapor ATS')
@section('heading', $schoolClass->name.' · '.$period->name)

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('reports.midterm.show', [$period, $schoolClass]) }}" class="text-sm text-cyan-300">← Kembali ke daftar rapor</a>
        <p class="text-xs text-slate-500">Wali kelas: {{ $schoolClass->homeroomTeacher?->name ?? 'belum ditetapkan' }}</p>
    </div>

    <section class="mt-6 rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.045] p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Alur Pengisian</p>
        <h2 class="mt-2 text-xl font-semibold text-white">Data rapor dibagi sesuai tugas guru</h2>
        <p class="mt-3 max-w-4xl text-sm leading-6 text-slate-400">Guru mapel mengisi TP, nilai, dan deskripsi. Guru pembina mengisi nilai ekstrakurikuler. Wali kelas mengisi sakit, izin, tanpa keterangan, dan catatan. Panitia serta super admin dapat membantu seluruh bagian.</p>
    </section>

    <section class="mt-6 space-y-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">01 · Nilai dan Capaian</p>
            <h2 class="mt-2 text-xl font-semibold text-white">Mata pelajaran</h2>
        </div>

        @forelse ($subjects as $assessmentSubject)
            @php($canEditSubject = $subjectPermissions[$assessmentSubject->id] ?? false)
            <details class="rounded-2xl border border-white/10 bg-white/[0.035]" @if($subjects->count() === 1) open @endif>
                <summary class="cursor-pointer list-none px-5 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-white">{{ $assessmentSubject->subject->name }}</p>
                            <p class="mt-1 text-xs text-slate-500">Guru: {{ $assessmentSubject->teacher?->name ?? 'belum ditetapkan' }} · {{ $canEditSubject ? 'dapat Anda isi' : 'hanya dapat dilihat' }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs {{ $assessmentSubject->learning_objective ? 'bg-emerald-400/10 text-emerald-200' : 'bg-amber-400/10 text-amber-200' }}">{{ $assessmentSubject->learning_objective ? 'TP tersedia' : 'TP belum diisi' }}</span>
                    </div>
                </summary>

                <div class="border-t border-white/10 p-5">
                    @if ($canEditSubject)
                        <form method="POST" action="{{ route('reports.midterm.subject-results.update', $assessmentSubject) }}">
                            @csrf @method('PUT')
                            <label class="block text-sm font-medium text-slate-200">Tujuan Pembelajaran (TP)
                                <textarea name="learning_objective" rows="3" required maxlength="2000" placeholder="Contoh: Peserta didik mampu menganalisis ..."
                                    class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm leading-6 outline-none focus:border-violet-400">{{ old('learning_objective', $assessmentSubject->learning_objective) }}</textarea>
                            </label>
                            <p class="mt-2 text-xs text-slate-500">Nilai CBT tampil sebagai nilai awal. Nilai dapat disesuaikan untuk asesmen kertas. Jika deskripsi dikosongkan, sistem membuat keterangan sesuai nilai dan TP.</p>

                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-full text-left text-xs">
                                    <thead class="border-b border-white/10 text-slate-500"><tr><th class="px-3 py-3">Peserta Didik</th><th class="w-28 px-3 py-3">Nilai</th><th class="min-w-96 px-3 py-3">Deskripsi Capaian</th></tr></thead>
                                    <tbody class="divide-y divide-white/5">
                                        @foreach ($rows->sortBy(fn ($item) => $item['student']->full_name) as $row)
                                            @php($result = $assessmentSubject->midtermResults->firstWhere('student_id', $row['student']->id))
                                            <tr>
                                                <td class="px-3 py-3"><span class="font-medium text-white">{{ $row['student']->full_name }}</span><span class="mt-1 block text-slate-600">{{ $row['student']->student_number }}</span></td>
                                                <td class="px-3 py-3"><input type="number" name="results[{{ $row['student']->id }}][score]" value="{{ old('results.'.$row['student']->id.'.score', $result?->score ?? $row['scores'][$assessmentSubject->id]) }}" min="0" max="100" step="0.01" class="w-24 rounded-lg border border-white/10 bg-slate-950 px-3 py-2"></td>
                                                <td class="px-3 py-3"><textarea name="results[{{ $row['student']->id }}][description]" rows="2" maxlength="2000" placeholder="Kosongkan untuk keterangan otomatis" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 leading-5">{{ old('results.'.$row['student']->id.'.description', $result?->description) }}</textarea></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <button class="mt-4 rounded-xl bg-violet-400 px-5 py-3 text-sm font-semibold text-slate-950">Simpan nilai dan deskripsi mapel</button>
                        </form>
                    @else
                        <p class="text-sm text-slate-500">Bagian ini diisi guru mata pelajaran yang ditetapkan pada Penjadwalan.</p>
                    @endif
                </div>
            </details>
        @empty
            <p class="rounded-2xl border border-dashed border-white/10 p-6 text-sm text-slate-500">Belum ada mata pelajaran pada periode dan kelas ini.</p>
        @endforelse
    </section>

    <section class="mt-8 space-y-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">02 · Ekstrakurikuler</p>
            <h2 class="mt-2 text-xl font-semibold text-white">Peserta dan nilai pembina</h2>
        </div>

        @if ($canConfigure)
            <form method="POST" action="{{ route('reports.midterm.extracurriculars.store', [$period, $schoolClass]) }}" class="grid gap-3 rounded-2xl border border-emerald-400/20 bg-emerald-400/[0.045] p-5 md:grid-cols-[1fr_1fr_auto]">
                @csrf
                <input name="name" required maxlength="120" list="extracurricular-options" placeholder="Nama ekstrakurikuler" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
                <datalist id="extracurricular-options"><option value="Badminton"><option value="Pramuka"><option value="Futsal"><option value="Paskibra"><option value="Tari"><option value="Paduan Suara"></datalist>
                <select name="coach_user_id" required class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm"><option value="">Pilih guru pembina</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach</select>
                <button class="rounded-xl bg-emerald-400 px-5 py-3 text-sm font-semibold text-slate-950">Tambah kegiatan</button>
            </form>
        @endif

        @forelse ($extracurriculars as $activity)
            @php($participantIds = $activity->participants->pluck('id'))
            @php($canGradeActivity = $extracurricularPermissions[$activity->id] ?? false)
            <details class="rounded-2xl border border-white/10 bg-white/[0.035]">
                <summary class="cursor-pointer list-none px-5 py-4"><div class="flex items-center justify-between gap-3"><div><p class="font-semibold text-white">{{ $activity->name }}</p><p class="mt-1 text-xs text-slate-500">Pembina: {{ $activity->coach?->name ?? 'belum ditetapkan' }} · {{ $participantIds->count() }} peserta di {{ $schoolClass->name }}</p></div><span class="text-xs text-emerald-300">Buka pengaturan</span></div></summary>
                <div class="space-y-5 border-t border-white/10 p-5">
                    @if ($canConfigure)
                        <form method="POST" action="{{ route('reports.midterm.extracurriculars.update', [$period, $schoolClass, $activity]) }}" class="flex flex-wrap items-center gap-3">
                            @csrf @method('PATCH')
                            <select name="coach_user_id" required class="min-w-64 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-xs">@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($activity->coach_user_id === $teacher->id)>{{ $teacher->name }}</option>@endforeach</select>
                            <label class="flex items-center gap-2 text-xs text-slate-300"><input type="checkbox" name="is_active" value="1" @checked($activity->is_active)> Aktif</label>
                            <button class="rounded-lg border border-emerald-400/30 px-4 py-2 text-xs font-semibold text-emerald-200">Simpan pembina</button>
                        </form>
                        <form method="POST" action="{{ route('reports.midterm.extracurriculars.participants', [$period, $schoolClass, $activity]) }}">
                            @csrf @method('PUT')
                            <p class="mb-3 text-sm font-medium text-slate-200">Pilih peserta kelas</p>
                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($rows->sortBy(fn ($item) => $item['student']->full_name) as $row)
                                    <label class="flex items-center gap-2 rounded-lg border border-white/10 px-3 py-2 text-xs text-slate-300"><input type="checkbox" name="participant_ids[]" value="{{ $row['student']->id }}" @checked($participantIds->contains($row['student']->id))> {{ $row['student']->full_name }}</label>
                                @endforeach
                            </div>
                            <button class="mt-3 rounded-lg border border-emerald-400/30 px-4 py-2 text-xs font-semibold text-emerald-200">Simpan peserta</button>
                        </form>
                    @endif

                    @if ($canGradeActivity && $activity->participants->isNotEmpty())
                        <form method="POST" action="{{ route('reports.midterm.extracurriculars.grades', [$period, $schoolClass, $activity]) }}">
                            @csrf @method('PUT')
                            <div class="overflow-x-auto"><table class="min-w-full text-left text-xs"><thead class="border-b border-white/10 text-slate-500"><tr><th class="px-3 py-3">Peserta</th><th class="w-44 px-3 py-3">Predikat</th><th class="min-w-96 px-3 py-3">Keterangan</th></tr></thead><tbody class="divide-y divide-white/5">
                                @foreach ($activity->participants as $student)
                                    @php($grade = $activity->grades->firstWhere('student_id', $student->id))
                                    <tr><td class="px-3 py-3 font-medium text-white">{{ $student->full_name }}</td><td class="px-3 py-3"><select name="ratings[{{ $student->id }}]" required class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2"><option value="">Pilih</option>@foreach($ratings as $rating)<option value="{{ $rating->value }}" @selected(old('ratings.'.$student->id, $grade?->rating?->value) === $rating->value)>{{ $rating->label() }}</option>@endforeach</select></td><td class="px-3 py-3"><textarea name="descriptions[{{ $student->id }}]" rows="2" maxlength="1000" placeholder="Kosongkan untuk keterangan otomatis" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 leading-5">{{ old('descriptions.'.$student->id, $grade?->description) }}</textarea></td></tr>
                                @endforeach
                            </tbody></table></div>
                            <button class="mt-4 rounded-xl bg-emerald-400 px-5 py-3 text-sm font-semibold text-slate-950">Simpan nilai ekstrakurikuler</button>
                        </form>
                    @elseif ($canGradeActivity)
                        <p class="text-sm text-amber-300">Belum ada peserta. Panitia atau super admin perlu memilih peserta terlebih dahulu.</p>
                    @endif
                </div>
            </details>
        @empty
            <p class="rounded-2xl border border-dashed border-white/10 p-6 text-sm text-slate-500">Belum ada data ekstrakurikuler pada tahun ajaran ini.</p>
        @endforelse
    </section>

    <section class="mt-8 rounded-3xl border border-amber-400/20 bg-amber-400/[0.035] p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-300">03 · Wali Kelas</p>
        <h2 class="mt-2 text-xl font-semibold text-white">Ketidakhadiran dan catatan</h2>
        @if ($canRecordAttendance)
            <form method="POST" action="{{ route('reports.midterm.attendance.update', [$period, $schoolClass]) }}" class="mt-5">
                @csrf @method('PUT')
                <div class="overflow-x-auto"><table class="min-w-full text-left text-xs"><thead class="border-b border-white/10 text-slate-500"><tr><th class="px-3 py-3">Peserta Didik</th><th class="w-24 px-3 py-3">Sakit</th><th class="w-24 px-3 py-3">Izin</th><th class="w-32 px-3 py-3">Tanpa Ket.</th><th class="min-w-80 px-3 py-3">Catatan Wali Kelas</th></tr></thead><tbody class="divide-y divide-white/5">
                    @foreach ($rows->sortBy(fn ($item) => $item['student']->full_name) as $row)
                        @php($attendance = $row['attendance'])
                        <tr><td class="px-3 py-3"><span class="font-medium text-white">{{ $row['student']->full_name }}</span><span class="mt-1 block text-slate-600">{{ $row['student']->student_number }}</span></td>@foreach(['sick_days','excused_days','unexcused_days'] as $field)<td class="px-3 py-3"><input type="number" name="attendance[{{ $row['student']->id }}][{{ $field }}]" value="{{ old('attendance.'.$row['student']->id.'.'.$field, $attendance->{$field}) }}" min="0" max="366" required class="w-20 rounded-lg border border-white/10 bg-slate-950 px-3 py-2"></td>@endforeach<td class="px-3 py-3"><textarea name="attendance[{{ $row['student']->id }}][notes]" rows="2" maxlength="1000" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 leading-5">{{ old('attendance.'.$row['student']->id.'.notes', $attendance->notes) }}</textarea></td></tr>
                    @endforeach
                </tbody></table></div>
                <button class="mt-4 rounded-xl bg-amber-400 px-5 py-3 text-sm font-semibold text-slate-950">Simpan ketidakhadiran dan catatan</button>
            </form>
        @else
            <p class="mt-4 text-sm text-slate-500">Bagian ini hanya dapat diisi oleh wali kelas yang ditetapkan, panitia, atau super admin.</p>
        @endif
    </section>
@endsection
