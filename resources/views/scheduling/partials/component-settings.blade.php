@php
    $componentLocked = $component->examSessions->isNotEmpty() || $component->assignments_count > 0 || $component->questions_count > 0;
@endphp
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

                    <details class="mt-4 rounded-2xl border border-white/10 bg-slate-950/35 p-4">
                        <summary class="cursor-pointer text-sm font-medium text-violet-300">Guru pengoreksi</summary>
                        <form method="POST" action="{{ route('scheduling.components.teacher', $component) }}" class="mt-4 flex flex-wrap gap-3">
                            @csrf @method('PATCH')
                            <select name="teacher_user_id" class="min-w-64 flex-1 rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                                <option value="">Belum ditentukan</option>
                                @foreach ($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" @selected($component->teacher_user_id === $teacher->id)>{{ $teacher->name }} · {{ $teacher->email }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-xl bg-violet-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Simpan guru</button>
                        </form>
                        <p class="mt-2 text-xs text-slate-500">Guru terpilih mengelola soal dan mengoreksi jawaban mapel ini. Rombel setingkat dengan guru yang sama berbagi bank soal.</p>
                    </details>

