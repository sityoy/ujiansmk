@extends('layouts.app')

@section('title', 'Kelola Rapor ATS '.$schoolClass->name)
@section('eyebrow', 'Entri Rapor ATS')
@section('heading', $schoolClass->name.' · '.$period->name)

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ $canPrint ? route('reports.midterm.show', [$period, $schoolClass]) : route('reports.midterm.index') }}" class="text-sm text-cyan-300">← Kembali ke daftar rapor</a>
        <p class="text-xs text-slate-500">Wali kelas: {{ $schoolClass->homeroomTeacher?->name ?? 'belum ditetapkan' }}</p>
    </div>

    <section class="mt-6 rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.045] p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Alur Pengisian</p>
        <h2 class="mt-2 text-xl font-semibold text-white">Data rapor dibagi sesuai tugas guru</h2>
        <p class="mt-3 max-w-4xl text-sm leading-6 text-slate-400">Guru mapel yang ditugaskan menyiapkan TP, mengisi nilai, dan memilih TP yang tercapai atau perlu ditingkatkan; capaian kompetensi dibuat otomatis. Guru pembina cukup memilih predikat ekstrakurikuler. Wali kelas mengisi ketidakhadiran dan catatan. Panitia mengatur kebutuhan cetak rapor.</p>
    </section>

    <section class="mt-6 space-y-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">01 · Nilai dan Capaian</p>
            <h2 class="mt-2 text-xl font-semibold text-white">Mata pelajaran</h2>
        </div>

        @forelse ($subjects as $assessmentSubject)
            @php($canEditSubject = $subjectPermissions[$assessmentSubject->id] ?? false)
            @php($canEditObjective = $learningObjectivePermissions[$assessmentSubject->id] ?? false)
            @php($objectiveList = $objectiveOptions[$assessmentSubject->id] ?? [])
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

                <div class="space-y-5 border-t border-white/10 p-5">
                    @if ($canEditObjective)
                        <form method="POST" action="{{ route('reports.midterm.learning-objective.update', $assessmentSubject) }}" class="rounded-xl border border-violet-400/15 bg-violet-400/[0.035] p-4">
                            @csrf @method('PUT')
                            <label class="block text-sm font-medium text-slate-200">Tujuan Pembelajaran (dasar capaian)
                                <textarea name="learning_objective" rows="4" required maxlength="4000" placeholder="Masukkan satu TP per baris."
                                    class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm leading-6 outline-none focus:border-violet-400">{{ old('learning_objective', $assessmentSubject->learning_objective) }}</textarea>
                            </label>
                            <p class="mt-2 text-xs text-slate-500">Satu baris untuk satu TP. TP hanya dapat dikelola oleh guru mapel yang ditugaskan. Jika TP diubah, capaian siswa yang sudah memiliki nilai ikut diperbarui.</p>
                            <button class="mt-3 rounded-lg border border-violet-400/30 px-4 py-2 text-xs font-semibold text-violet-200">Simpan TP mapel</button>
                        </form>
                    @else
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-violet-300">Tujuan Pembelajaran</p>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-300">{{ $assessmentSubject->learning_objective ?: 'TP belum disiapkan oleh guru mata pelajaran.' }}</p>
                        </div>
                    @endif

                    @if ($canEditSubject)
                        @if ($assessmentSubject->learning_objective)
                        <form method="POST" action="{{ route('reports.midterm.subject-results.update', $assessmentSubject) }}" data-learning-outcome-form>
                            @csrf @method('PUT')
                            <p class="text-xs text-slate-500">Masukkan nilai akhir, lalu pilih TP yang tercapai optimal dan yang perlu ditingkatkan. Satu TP hanya dapat dipilih pada salah satu kolom. Jika tidak ada pilihan, sistem menentukannya otomatis berdasarkan nilai.</p>
                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-full text-left text-xs">
                                    <thead class="border-b border-white/10 text-slate-500"><tr><th class="min-w-48 px-3 py-3">Peserta Didik</th><th class="w-28 px-3 py-3">Nilai Akhir</th><th class="min-w-[24rem] px-3 py-3">TP Tercapai Optimal</th><th class="min-w-[24rem] px-3 py-3">TP Perlu Peningkatan</th></tr></thead>
                                    <tbody class="divide-y divide-white/5">
                                        @foreach ($rows->sortBy(fn ($item) => $item['student']->full_name) as $row)
                                            @php($result = $assessmentSubject->midtermResults->firstWhere('student_id', $row['student']->id))
                                            @php($currentScore = $result?->score ?? $row['scores'][$assessmentSubject->id])
                                            @php($defaultAchieved = $result?->achieved_objectives ?? ($currentScore !== null && (float) $currentScore >= 76 ? $objectiveList : []))
                                            @php($defaultImprovement = $result?->improvement_objectives ?? ($currentScore !== null && (float) $currentScore < 76 ? $objectiveList : []))
                                            @php($achievedSelections = collect(old('results.'.$row['student']->id.'.achieved_objectives', $defaultAchieved)))
                                            @php($improvementSelections = collect(old('results.'.$row['student']->id.'.improvement_objectives', $defaultImprovement)))
                                            <tr>
                                                <td class="px-3 py-3"><span class="font-medium text-white">{{ $row['student']->full_name }}</span><span class="mt-1 block text-slate-600">NIS/NISN: {{ $row['student']->student_number }} / {{ $row['student']->nisn ?? '—' }}</span>@if($result?->description)<span class="mt-3 block text-[11px] leading-5 text-slate-500">{{ $result->description }}</span>@endif</td>
                                                <td class="px-3 py-3"><input type="number" name="results[{{ $row['student']->id }}][score]" value="{{ old('results.'.$row['student']->id.'.score', $currentScore) }}" min="0" max="100" step="0.01" class="w-24 rounded-lg border border-white/10 bg-slate-950 px-3 py-2"></td>
                                                <td class="space-y-2 px-3 py-3">
                                                    @foreach ($objectiveList as $objective)
                                                        <label class="flex items-start gap-2 leading-5 text-slate-300 transition-opacity" data-objective-label><input type="checkbox" name="results[{{ $row['student']->id }}][achieved_objectives][]" value="{{ $objective }}" @checked($achievedSelections->containsStrict($objective)) data-objective-choice data-student="{{ $row['student']->id }}" data-objective-index="{{ $loop->index }}" data-outcome="achieved" class="mt-1"> <span>{{ $objective }}</span></label>
                                                    @endforeach
                                                </td>
                                                <td class="space-y-2 px-3 py-3">
                                                    @foreach ($objectiveList as $objective)
                                                        <label class="flex items-start gap-2 leading-5 text-slate-300 transition-opacity" data-objective-label><input type="checkbox" name="results[{{ $row['student']->id }}][improvement_objectives][]" value="{{ $objective }}" @checked($improvementSelections->containsStrict($objective)) data-objective-choice data-student="{{ $row['student']->id }}" data-objective-index="{{ $loop->index }}" data-outcome="improvement" class="mt-1"> <span>{{ $objective }}</span></label>
                                                    @endforeach
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <button class="mt-4 rounded-xl bg-violet-400 px-5 py-3 text-sm font-semibold text-slate-950">Simpan nilai</button>
                        </form>
                        @else
                            <p class="rounded-xl border border-amber-400/20 bg-amber-400/10 p-4 text-sm text-amber-200">Nilai belum dapat diisi karena guru mata pelajaran yang ditugaskan belum menyiapkan TP.</p>
                        @endif
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
                            <p class="mb-3 text-xs text-slate-500">Pembina cukup memilih predikat. Keterangan ekstrakurikuler dibuat otomatis oleh sistem.</p>
                            <div class="overflow-x-auto"><table class="min-w-full text-left text-xs"><thead class="border-b border-white/10 text-slate-500"><tr><th class="px-3 py-3">Peserta</th><th class="w-44 px-3 py-3">Predikat</th><th class="min-w-96 px-3 py-3">Keterangan Otomatis</th></tr></thead><tbody class="divide-y divide-white/5">
                                @foreach ($activity->participants as $student)
                                    @php($grade = $activity->grades->firstWhere('student_id', $student->id))
                                    <tr><td class="px-3 py-3 font-medium text-white">{{ $student->full_name }}</td><td class="px-3 py-3"><select name="ratings[{{ $student->id }}]" required class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2"><option value="">Pilih</option>@foreach($ratings as $rating)<option value="{{ $rating->value }}" @selected(old('ratings.'.$student->id, $grade?->rating?->value) === $rating->value)>{{ $rating->label() }}</option>@endforeach</select></td><td class="px-3 py-3 leading-5 text-slate-400">{{ $grade?->description ?: 'Akan dibuat setelah predikat disimpan.' }}</td></tr>
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

    <script>
        document.querySelectorAll('[data-learning-outcome-form]').forEach((form) => {
            const pairs = new Map();

            form.querySelectorAll('[data-objective-choice]').forEach((checkbox) => {
                const key = `${checkbox.dataset.student}:${checkbox.dataset.objectiveIndex}`;
                const pair = pairs.get(key) ?? {};
                pair[checkbox.dataset.outcome] = checkbox;
                pairs.set(key, pair);
            });

            pairs.forEach(({ achieved, improvement }) => {
                if (! achieved || ! improvement) {
                    return;
                }

                if (achieved.checked && improvement.checked) {
                    improvement.checked = false;
                }

                const syncPair = () => {
                    improvement.disabled = achieved.checked;
                    achieved.disabled = improvement.checked;
                    improvement.closest('[data-objective-label]')?.classList.toggle('opacity-40', achieved.checked);
                    achieved.closest('[data-objective-label]')?.classList.toggle('opacity-40', improvement.checked);
                };

                achieved.addEventListener('change', syncPair);
                improvement.addEventListener('change', syncPair);
                syncPair();
            });
        });
    </script>
@endsection
