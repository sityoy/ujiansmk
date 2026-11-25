@extends('layouts.app')

@section('title', 'Soal Uji Coba')
@section('eyebrow', 'Bank Soal Uji Coba')
@section('heading', 'Kelola Soal Uji Coba')

@section('content')
    @php($perPage = \App\Support\TablePagination::selected())
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
            <a href="{{ route('trial-question-banks.questions.preview', [$bank, 'per_page' => $perPage, 'page' => $questions->currentPage()]) }}" class="inline-flex rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Simulasi tampilan peserta</a>
            <a href="{{ route('trial-question-banks.questions.export', $bank) }}" class="inline-flex rounded-xl border border-emerald-400/25 bg-emerald-400/10 px-4 py-2.5 text-sm font-semibold text-emerald-200">Ekspor soal ke Excel</a>
            @if ($bank->source_pack_key && ! $bank->archived_at && auth()->user()->role !== \App\Enums\UserRole::Teacher)
                <x-trial-pack-cancel :bank="$bank" />
            @endif
        </div>
        @if ($isLocked)<p class="mt-3 text-sm text-amber-300">{{ $lockedReason }}</p>@endif
    </section>

    <div class="mt-6 space-y-6">
        @unless ($isLocked)
            <details data-question-add class="rounded-3xl border border-white/10 bg-white/[0.035] p-5 sm:p-6" @if ($questionCount === 0 || $errors->any()) open @endif>
                <summary class="cursor-pointer text-base font-semibold text-cyan-300">Tambah soal</summary>
                <p class="mt-3 text-xs leading-5 text-slate-400">Pilihan Ganda dinilai otomatis. Isian Singkat dan Esai dinilai manual sesuai bobot yang diisi.</p>
                <div class="mt-5 max-w-3xl">
                    <x-question-bank-form :action="route('trial-question-banks.questions.store', [$bank, 'per_page' => $perPage])" :question-types="\App\Enums\ExamQuestionType::cases()" trial />
                </div>
            </details>
        @endunless

        <section aria-labelledby="bank-questions-title" class="space-y-3">
            <h2 id="bank-questions-title" class="text-lg font-semibold text-white">Daftar soal</h2>
            @forelse ($questions as $question)
                <article id="bank-question-{{ $question->id }}" class="scroll-mt-24 rounded-2xl border border-white/10 bg-white/[0.035] p-5 target:border-cyan-400/60">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-cyan-300">Soal {{ $question->position }} · {{ $question->question_type->label() }} · {{ (float) $question->points }} poin</p>
                        @unless ($isLocked)
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('trial-question-banks.questions.edit', [$bank, $question, 'per_page' => $perPage]) }}"
                                    class="rounded-lg border border-cyan-400/35 bg-cyan-400/10 px-3 py-2 text-sm font-semibold text-cyan-200">Edit soal</a>
                                <form method="POST" action="{{ route('trial-question-banks.questions.destroy', [$bank, $question]) }}" onsubmit="return confirm('Hapus soal ini?')">
                                    @csrf @method('DELETE')
                                    <button class="px-3 py-2 text-sm text-rose-300">Hapus</button>
                                </form>
                            </div>
                        @endunless
                    </div>
                    @if (session('saved_question_id') === $question->id)
                        <p role="status" class="mt-2 text-sm font-semibold text-emerald-200">Soal berhasil disimpan.</p>
                    @endif
                    <x-question-prompt :question="$question" class="mt-3" />
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        @foreach (collect($question->options)->sortKeys() as $key => $option)
                            <div class="rounded-xl border px-3 py-2 text-xs {{ $key === $question->correct_answer ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200' : 'border-white/10 text-slate-400' }}"><div class="flex items-start gap-2"><strong class="shrink-0">{{ $key }}.</strong><x-question-text :text="$option" :format="$question->text_format" /></div>
                                @if ($question->option_file_paths[$key] ?? null)
                                    <x-question-attachment :path="$question->option_file_paths[$key]" :alt="'lampiran pilihan '.$key" class="mt-2" />
                                @endif
                            </div>
                        @endforeach
                    </div>                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-white/10 p-12 text-center text-sm text-slate-500">Belum ada soal dalam bank uji coba ini.</div>
            @endforelse
        </section>
    </div>
    <div class="mt-6 rounded-2xl border border-white/10 p-4">{{ $questions->links() }}</div>
    <x-exam-image-dialog />
@endsection
