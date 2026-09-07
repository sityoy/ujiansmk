@extends('layouts.app')

@section('title', 'Bank Soal')
@section('eyebrow', 'Penjadwalan')
@section('heading', 'Bank Soal Ujian')

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-3xl border border-violet-400/20 bg-violet-400/[0.045] p-6">
        <a href="{{ route('question-bank.index') }}" class="text-xs font-medium text-violet-300">← Kembali ke daftar bank soal</a>
        <p class="mt-5 text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">{{ $assessmentSubject->assessmentPeriod->name }}</p>
        <h2 class="mt-2 text-2xl font-semibold text-white">{{ $assessmentSubject->subject->name }} · {{ $assessmentSubject->schoolClass->name }}</h2>
        <p class="mt-2 text-sm text-slate-400">{{ $assessmentSubject->assessmentPeriod->academicYear->name }} · {{ $assessmentSubject->questions->count() }} soal · Total bobot {{ number_format((float) $assessmentSubject->questions->sum('points'), 2, ',', '.') }}</p>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[420px_1fr]">
        <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            <h2 class="text-xl font-semibold text-white">Tambah soal</h2>
            @if ($assessmentSubject->assessmentPeriod->type->value === 'ats')
                <p class="mt-2 text-sm text-cyan-200">Khusus ATS: tersedia isian singkat dan esai. Seluruh jawaban dikoreksi manual.</p>
                <p class="mt-2 rounded-xl border border-cyan-400/15 bg-cyan-400/[0.04] p-3 text-xs leading-5 text-slate-400">Saran 20 soal: 10 isian × 3 poin + 10 esai × 7 poin = total 100. Bobot wajib diisi dan merupakan nilai maksimal setiap soal.</p>
            @endif
            @if ($isLocked)
                <p class="mt-3 text-sm text-amber-300">Bank soal terkunci karena sudah ada siswa yang mulai ujian. Isi dan bobot soal dipertahankan untuk menjaga konsistensi nilai reguler dan susulan.</p>
            @endif
            <fieldset @disabled($isLocked)>
            <form method="POST" action="{{ route('scheduling.questions.store', $assessmentSubject) }}" class="mt-5 space-y-3">
                @csrf
                <select id="question-type" name="question_type" required class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                    @foreach ($questionTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('question_type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                <textarea name="question_text" rows="5" required placeholder="Tuliskan pertanyaan..." class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-violet-400">{{ old('question_text') }}</textarea>
                <div id="multiple-choice-fields" class="space-y-3">
                    @foreach (['A', 'B', 'C', 'D'] as $option)
                        <div class="grid grid-cols-[36px_1fr] items-center gap-2">
                            <span class="grid size-9 place-items-center rounded-lg bg-slate-900 text-xs font-semibold text-violet-300">{{ $option }}</span>
                            <input name="option_{{ strtolower($option) }}" value="{{ old('option_'.strtolower($option)) }}" placeholder="Pilihan {{ $option }}" class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-violet-400">
                        </div>
                    @endforeach
                    <select name="correct_answer" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                        <option value="">Jawaban benar</option>
                        @foreach (['A', 'B', 'C', 'D'] as $option)<option value="{{ $option }}">Pilihan {{ $option }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="question-points" class="mb-2 block text-xs font-medium text-slate-400">Bobot maksimal soal</label>
                    <input id="question-points" type="number" name="points" value="{{ old('points', $assessmentSubject->assessmentPeriod->type->value === 'ats' ? 3 : 1) }}" min="0.01" max="1000" step="0.01" required placeholder="Bobot" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                </div>
                <p class="text-xs leading-5 text-slate-500">Nilai akhir dihitung: poin diperoleh ÷ total bobot × 100. Total bobot disarankan 100 agar mudah diperiksa.</p>
                <button class="w-full rounded-xl bg-violet-400 px-4 py-3 text-sm font-semibold text-slate-950">Simpan soal</button>
            </form>
            </fieldset>
        </section>

        <section class="space-y-3">
            @forelse ($assessmentSubject->questions as $question)
                <article class="rounded-2xl border border-white/10 bg-white/[0.035] p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold text-violet-300">Soal {{ $question->position }} · {{ $question->question_type->label() }} · {{ number_format((float) $question->points, 2, ',', '.') }} poin</p>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-white">{{ $question->question_text }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            @unless ($isLocked)
                                <button type="button" data-edit-toggle="question-edit-{{ $question->id }}" class="text-xs font-medium text-cyan-300">Edit</button>
                            @endunless
                            <form method="POST" action="{{ route('scheduling.questions.destroy', [$assessmentSubject, $question]) }}" onsubmit="return confirm('Hapus soal ini?')">
                                @csrf @method('DELETE')
                                <button @disabled($isLocked) class="text-xs text-rose-300 disabled:opacity-40">Hapus</button>
                            </form>
                        </div>
                    </div>
                    @if ($question->question_type->value === 'multiple_choice')
                        <div class="mt-4 grid gap-2 sm:grid-cols-2">
                            @foreach ($question->options as $key => $option)
                                <div class="rounded-xl border px-3 py-2 text-xs {{ $key === $question->correct_answer ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200' : 'border-white/10 text-slate-400' }}">
                                    <span class="font-semibold">{{ $key }}.</span> {{ $option }}
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 text-xs text-amber-200">Tidak memakai kunci otomatis; guru memberi nilai setelah ujian dikumpulkan.</p>
                    @endif

                    @unless ($isLocked)
                        <form id="question-edit-{{ $question->id }}" method="POST" action="{{ route('scheduling.questions.update', [$assessmentSubject, $question]) }}" class="question-edit-form mt-5 hidden space-y-3 border-t border-white/10 pt-5">
                            @csrf @method('PUT')
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-cyan-300">Edit soal {{ $question->position }}</p>
                            <select name="question_type" required class="edit-question-type w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                                @foreach ($questionTypes as $type)
                                    <option value="{{ $type->value }}" @selected($question->question_type === $type)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            <textarea name="question_text" rows="4" required class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400">{{ $question->question_text }}</textarea>
                            <div class="edit-choice-fields space-y-3">
                                @foreach (['A', 'B', 'C', 'D'] as $option)
                                    <div class="grid grid-cols-[36px_1fr] items-center gap-2">
                                        <span class="grid size-9 place-items-center rounded-lg bg-slate-900 text-xs font-semibold text-violet-300">{{ $option }}</span>
                                        <input name="option_{{ strtolower($option) }}" value="{{ $question->options[$option] ?? '' }}" placeholder="Pilihan {{ $option }}" class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400">
                                    </div>
                                @endforeach
                                <select name="correct_answer" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                                    <option value="">Jawaban benar</option>
                                    @foreach (['A', 'B', 'C', 'D'] as $option)
                                        <option value="{{ $option }}" @selected($question->correct_answer === $option)>Pilihan {{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-medium text-slate-400">Bobot maksimal soal</label>
                                <input type="number" name="points" value="{{ $question->points }}" min="0.01" max="1000" step="0.01" required class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Simpan perubahan</button>
                                <button type="button" data-edit-toggle="question-edit-{{ $question->id }}" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm text-slate-300">Batal</button>
                            </div>
                        </form>
                    @endunless
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-white/10 p-12 text-center text-sm text-slate-500">Belum ada soal untuk mapel dan kelas ini.</div>
            @endforelse
        </section>
    </div>
    <script>
        const questionType = document.getElementById('question-type');
        const choiceFields = document.getElementById('multiple-choice-fields');
        const questionPoints = document.getElementById('question-points');
        let pointsEdited = @json(old('points') !== null);
        questionPoints.addEventListener('input', () => { pointsEdited = true; });
        const syncQuestionType = () => {
            const enabled = questionType.value === 'multiple_choice';
            choiceFields.hidden = !enabled;
            choiceFields.querySelectorAll('input, select').forEach(field => { field.required = enabled; field.disabled = !enabled; });
            if (!pointsEdited) {
                questionPoints.value = questionType.value === 'essay' ? '7' : (questionType.value === 'short_answer' ? '3' : '1');
            }
        };
        questionType.addEventListener('change', syncQuestionType);
        syncQuestionType();

        document.querySelectorAll('[data-edit-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById(button.dataset.editToggle)?.classList.toggle('hidden');
            });
        });

        document.querySelectorAll('.question-edit-form').forEach((form) => {
            const type = form.querySelector('.edit-question-type');
            const fields = form.querySelector('.edit-choice-fields');
            const syncEditType = () => {
                const enabled = type.value === 'multiple_choice';
                fields.hidden = !enabled;
                fields.querySelectorAll('input, select').forEach((field) => {
                    field.required = enabled;
                    field.disabled = !enabled;
                });
            };
            type.addEventListener('change', syncEditType);
            syncEditType();
        });
    </script>
@endsection
