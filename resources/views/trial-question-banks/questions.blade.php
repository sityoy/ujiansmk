@extends('layouts.app')

@section('title', 'Soal Uji Coba')
@section('eyebrow', 'Bank Soal Uji Coba')
@section('heading', 'Kelola Soal Uji Coba')

@section('content')
    @if ($errors->any())<div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>@endif

    <section class="rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.045] p-6">
        <a href="{{ route('trial-question-banks.index') }}" class="text-xs text-cyan-300">← Kembali ke bank soal uji coba</a>
        <p class="mt-5 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">{{ $bank->subject->name }} · Kelas {{ $bank->grade_level }}</p>
        <h2 class="mt-2 text-2xl font-semibold text-white">{{ $bank->name }}</h2>
        <p class="mt-2 text-sm text-slate-400">{{ $questionCount }} soal · Total bobot {{ number_format($totalPoints, 2, ',', '.') }}</p>
        <p class="mt-2 text-xs text-cyan-200">Setiap jawaban PG benar bernilai tetap 2 poin. Nilai PG maksimal 100.</p>
        <p class="mt-2 text-sm text-slate-400">Guru {{ $bank->teacher?->name ?? 'belum ditetapkan' }}</p>
        <p class="mt-2 text-sm text-slate-400">Batas pembuatan soal · {{ $bank->questionDeadlineLabel() ?? 'Belum ditetapkan' }}</p>
        @if (auth()->user()->role !== \App\Enums\UserRole::Teacher && ! $bank->archived_at)
            <details class="mt-4 rounded-xl border border-white/10 p-4" @if ($errors->hasAny(['question_deadline_at', 'teacher_user_id'])) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-cyan-300">Pengaturan bank soal</summary>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <form method="POST" action="{{ route('trial-question-banks.settings.update', $bank) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <label class="block text-sm text-slate-300">Guru penanggung jawab
                            <select name="teacher_user_id" class="mt-2 block w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                                <option value="">Belum ditetapkan</option>
                                @foreach ($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" @selected($teacher->id == old('teacher_user_id', $bank->teacher_user_id))>{{ $teacher->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-cyan-300">Simpan guru</button>
                    </form>
                    <form method="POST" action="{{ route('trial-question-banks.settings.update', $bank) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <label class="block text-sm text-slate-300">Tanggal dan jam batas pembuatan soal (WIB)
                            <input type="datetime-local" name="question_deadline_at" step="60"
                                value="{{ old('question_deadline_at', $bank->question_deadline_at?->timezone($bank::QUESTION_TIMEZONE)->format('Y-m-d\TH:i')) }}"
                                class="mt-2 block w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                        </label>
                        <p class="text-xs text-slate-400">Kosongkan lalu simpan untuk menghapus batas waktu.</p>
                        <button type="submit" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-cyan-300">Simpan batas</button>
                    </form>
                </div>
            </details>
        @endif
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('trial-question-banks.questions.export', $bank) }}" class="inline-flex rounded-xl border border-emerald-400/25 bg-emerald-400/10 px-4 py-2.5 text-sm font-semibold text-emerald-200">Ekspor soal ke Excel</a>
            @if ($bank->source_pack_key && ! $bank->archived_at && auth()->user()->role !== \App\Enums\UserRole::Teacher)
                <x-trial-pack-cancel :bank="$bank" />
            @endif
        </div>
        @if ($isLocked)<p class="mt-3 text-sm text-amber-300">{{ $lockedReason }}</p>@endif
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[420px_1fr]">
        <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            <h2 class="text-xl font-semibold text-white">Tambah soal</h2>
            <p class="mt-2 text-xs leading-5 text-slate-500">Pilihan Ganda dinilai otomatis. Isian Singkat dan Esai dinilai manual sesuai bobot yang diisi.</p>
            <fieldset @disabled($isLocked)>
                <form method="POST" action="{{ route('trial-question-banks.questions.store', $bank) }}" enctype="multipart/form-data" data-trial-question-form class="mt-5 space-y-3">
                    @csrf
                    @include('trial-question-banks.question-type', ['question' => null])
                    <x-question-prompt-fields />
                    <div data-trial-choice-fields class="space-y-3">
                        @foreach (['A','B','C','D','E'] as $option)
                            <div class="grid grid-cols-[36px_1fr] items-start gap-2">
                                <span class="mt-1 grid size-9 place-items-center rounded-lg bg-slate-900 text-xs font-semibold text-cyan-300">{{ $option }}</span>
                                <div class="space-y-2">
                                    <x-question-text-editor name="option_{{ strtolower($option) }}" :value="old('option_'.strtolower($option))"
                                        :format="old('text_format', 'plain')" label="Pilihan {{ $option }}" :show-label="false" :rows="2" :limit="2000" required placeholder="Pilihan {{ $option }}" />
                                    <x-question-file-input :name="'option_file_'.strtolower($option)" :label="'Lampiran pilihan '.$option" :alt="'Gambar pilihan '.$option" :show-label="false" class="w-full rounded-lg border border-dashed border-white/10 bg-slate-950/50 px-3 py-2 text-xs text-slate-500" />
                                </div>
                            </div>
                        @endforeach
                        <select name="correct_answer" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm"><option value="">Jawaban benar</option>@foreach (['A','B','C','D','E'] as $option)<option value="{{ $option }}" @selected(old('correct_answer') === $option)>Pilihan {{ $option }}</option>@endforeach</select>
                        <div class="rounded-xl border border-cyan-400/15 bg-cyan-400/[0.04] p-3 text-sm text-cyan-100">Bobot PG otomatis: <strong>2 poin</strong></div>
                    </div>
                    @include('trial-question-banks.manual-points', ['question' => null])
                    <button class="w-full rounded-xl bg-cyan-400 px-4 py-3 font-semibold text-slate-950">Simpan soal</button>
                </form>
            </fieldset>
        </section>

        <section class="space-y-3">
            @forelse ($questions as $question)
                <article class="rounded-2xl border border-white/10 bg-white/[0.035] p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold text-cyan-300">Soal {{ $question->position }} · {{ $question->question_type->label() }} · {{ (float) $question->points }} poin</p>
                            <x-question-prompt :question="$question" class="mt-2" />
                        </div>
                        @unless ($isLocked)<div class="flex shrink-0 gap-3"><button type="button" data-edit-toggle="trial-edit-{{ $question->id }}" class="text-xs text-cyan-300">Edit</button><form method="POST" action="{{ route('trial-question-banks.questions.destroy', [$bank, $question]) }}" onsubmit="return confirm('Hapus soal ini?')">@csrf @method('DELETE')<button class="text-xs text-rose-300">Hapus</button></form></div>@endunless
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        @foreach (collect($question->options)->sortKeys() as $key => $option)
                            <div class="rounded-xl border px-3 py-2 text-xs {{ $key === $question->correct_answer ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200' : 'border-white/10 text-slate-400' }}"><div class="flex items-start gap-2"><strong class="shrink-0">{{ $key }}.</strong><x-question-text :text="$option" :format="$question->text_format" /></div>
                                @if ($question->option_file_paths[$key] ?? null)
                                    <x-question-attachment :path="$question->option_file_paths[$key]" :alt="'lampiran pilihan '.$key" class="mt-2" />
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @unless ($isLocked)
                        <form id="trial-edit-{{ $question->id }}" method="POST" action="{{ route('trial-question-banks.questions.update', [$bank, $question]) }}" enctype="multipart/form-data" data-trial-question-form class="mt-5 hidden space-y-3 border-t border-white/10 pt-5">
                            @csrf @method('PUT')
                            @include('trial-question-banks.question-type')
                            <x-question-prompt-fields :question="$question" />
                            <div data-trial-choice-fields class="space-y-3">
                                @foreach (['A','B','C','D','E'] as $option)
                                    <div class="space-y-2 rounded-xl border border-white/10 p-3">
                                        <x-question-text-editor name="option_{{ strtolower($option) }}" :value="$question->options[$option] ?? ''"
                                            :format="$question->text_format" label="Pilihan {{ $option }}" :show-label="false" :rows="2" :limit="2000" required placeholder="Pilihan {{ $option }}" />
                                        <x-question-file-input :name="'option_file_'.strtolower($option)" :label="'Lampiran pilihan '.$option" :alt="'Gambar pilihan '.$option" :path="$question->option_file_paths[$option] ?? null" :show-label="false" class="w-full text-xs text-slate-500" />
                                    </div>
                                @endforeach
                                <select name="correct_answer" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">@foreach (['A','B','C','D','E'] as $option)<option value="{{ $option }}" @selected($question->correct_answer === $option)>Jawaban {{ $option }}</option>@endforeach</select>
                            </div>
                            @include('trial-question-banks.manual-points')
                            <div class="flex gap-2"><button class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Simpan perubahan</button><button type="button" data-edit-toggle="trial-edit-{{ $question->id }}" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm">Batal</button></div>
                        </form>
                    @endunless
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-white/10 p-12 text-center text-sm text-slate-500">Belum ada soal dalam bank uji coba ini.</div>
            @endforelse
        </section>
    </div>
    <div class="mt-6 rounded-2xl border border-white/10 p-4">{{ $questions->links() }}</div>
    <x-exam-image-dialog />
    <script>
        document.querySelectorAll('[data-trial-question-form]').forEach(form => {
            const type = form.elements.question_type;
            const choices = form.querySelector('[data-trial-choice-fields]');
            const points = form.querySelector('[data-trial-manual-points]');
            const input = points.querySelector('input');
            let pointsEdited = input.value !== '';
            input.addEventListener('input', () => { pointsEdited = true; });
            const toggleFields = () => {
                const multipleChoice = type.value === 'multiple_choice';
                choices.hidden = !multipleChoice;
                choices.querySelectorAll('input, textarea, select').forEach(field => {
                    field.disabled = !multipleChoice;
                    if (field.matches('[data-question-editor]')) field.dataset.editorRequired = String(multipleChoice);
                });
                points.hidden = multipleChoice;
                input.disabled = multipleChoice;
                if (!multipleChoice && !pointsEdited) input.value = type.value === 'short_answer' ? '3' : '7';
            };
            type.addEventListener('change', toggleFields);
            toggleFields();
        });
        document.querySelectorAll('[data-edit-toggle]').forEach(button => {
            button.addEventListener('click', () => document.getElementById(button.dataset.editToggle)?.classList.toggle('hidden'));
        });
    </script>
@endsection
