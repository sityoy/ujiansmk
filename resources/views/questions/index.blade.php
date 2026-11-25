@extends('layouts.app')

@section('title', 'Bank Soal Ujian Inti')
@section('eyebrow', 'Penjadwalan')
@section('heading', 'Bank Soal Ujian Inti')

@section('content')
    @php($perPage = \App\Support\TablePagination::selected())
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-3xl border border-violet-400/20 bg-violet-400/[0.045] p-6">
        <a href="{{ route('question-bank.index') }}" class="text-xs font-medium text-violet-300">← Kembali ke daftar bank soal</a>
        <p class="mt-5 text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">{{ $assessmentSubject->assessmentPeriod->name }}</p>
        <h2 class="mt-2 text-2xl font-semibold text-white">{{ $assessmentSubject->subject->name }} · Kelas {{ $gradeLevel }}</h2>
        <p class="mt-2 text-sm text-slate-400">{{ $assessmentSubject->assessmentPeriod->academicYear->name }} · {{ $questionCount }} soal · Total bobot {{ number_format($totalPoints, 2, ',', '.') }}</p>
        <p class="mt-2 text-xs text-cyan-200">Rombel {{ $rombelNames }} · Guru {{ $assessmentSubject->teacher?->name ?? "belum ditetapkan" }}</p>
        <x-core-question-deadline :period="$assessmentSubject->assessmentPeriod" class="mt-3 text-sm" />
        @if (auth()->user()->role !== \App\Enums\UserRole::Teacher)
            <a href="{{ route('question-bank.index', ['period_id' => $assessmentSubject->assessment_period_id]) }}#batas-pembuatan-soal" class="mt-2 inline-block text-xs font-semibold text-violet-300">Atur batas pembuatan soal periode ini</a>
        @endif
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('scheduling.questions.preview', [$assessmentSubject, 'per_page' => $perPage, 'page' => $questions->currentPage()]) }}" class="inline-flex rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Simulasi tampilan peserta</a>
            <a href="{{ route('scheduling.questions.export', $assessmentSubject) }}" class="inline-flex rounded-xl border border-emerald-400/25 bg-emerald-400/10 px-4 py-2.5 text-sm font-semibold text-emerald-200">Ekspor PG ke Excel</a>
        </div>
    </section>

    <div class="mt-6 space-y-6">
        @if ($hasAttempts)
            <p class="mt-3 text-sm text-amber-300">Bank soal terkunci karena sudah ada siswa yang mulai ujian. Isi dan bobot soal dipertahankan untuk menjaga konsistensi nilai reguler dan susulan.</p>
        @elseif ($isLocked)
            <p class="mt-3 text-sm text-amber-300">Batas pembuatan soal sudah lewat. Hubungi Panitia atau Super Admin untuk mengubah batas periode.</p>
        @endif
        @unless ($isLocked)
            <details data-question-add class="rounded-3xl border border-white/10 bg-white/[0.035] p-5 sm:p-6" @if ($questionCount === 0 || $errors->any()) open @endif>
                <summary class="cursor-pointer text-base font-semibold text-cyan-300">Tambah soal</summary>
                @if ($assessmentSubject->assessmentPeriod->type->value === 'ats')
                    <p class="mt-3 text-sm text-cyan-200">Khusus ATS: tersedia isian singkat dan esai. Seluruh jawaban dikoreksi manual.</p>
                    <p class="mt-2 rounded-xl border border-cyan-400/15 bg-cyan-400/[0.04] p-3 text-xs leading-5 text-slate-400">Saran 20 soal: 10 isian × 3 poin + 10 esai × 7 poin = total 100. Bobot wajib diisi dan merupakan nilai maksimal setiap soal.</p>
                @endif
                <div class="mt-5 max-w-3xl">
                    <x-question-bank-form :action="route('scheduling.questions.store', [$assessmentSubject, 'per_page' => $perPage])" :question-types="$questionTypes" />
                </div>
            </details>
        @endunless

        <section aria-labelledby="bank-questions-title" class="space-y-3">
            <h2 id="bank-questions-title" class="text-lg font-semibold text-white">Daftar soal</h2>
            @forelse ($questions as $question)
                <article id="bank-question-{{ $question->id }}" class="scroll-mt-24 rounded-2xl border border-white/10 bg-white/[0.035] p-5 target:border-cyan-400/60">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-violet-300">Soal {{ $question->position }} · {{ $question->question_type->label() }} · {{ number_format((float) $question->points, 2, ',', '.') }} poin</p>
                        @unless ($isLocked)
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('scheduling.questions.edit', [$assessmentSubject, $question, 'per_page' => $perPage]) }}"
                                    class="rounded-lg border border-cyan-400/35 bg-cyan-400/10 px-3 py-2 text-sm font-semibold text-cyan-200">Edit soal</a>
                                <form method="POST" action="{{ route('scheduling.questions.destroy', [$assessmentSubject, $question]) }}" onsubmit="return confirm('Hapus soal ini?')">
                                    @csrf @method('DELETE')
                                    <button class="px-3 py-2 text-sm text-rose-300">Hapus</button>
                                </form>
                            </div>
                        @endunless
                    </div>
                    @if (session('saved_question_id') === $question->id)
                        <p role="status" class="mt-2 text-sm font-semibold text-emerald-200">Soal berhasil disimpan.</p>
                    @endif
                    <x-question-prompt :question="$question" :multiple-choice="$question->question_type->value === 'multiple_choice'" class="mt-3" />
                    @if ($question->question_type->value === 'multiple_choice')
                        <div class="mt-4 grid gap-2 sm:grid-cols-2">
                            @foreach (collect($question->options)->sortKeys() as $key => $option)
                                <div class="rounded-xl border px-3 py-2 text-xs {{ $key === $question->correct_answer ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200' : 'border-white/10 text-slate-400' }}">
                                    <div class="flex items-start gap-2"><span class="shrink-0 font-semibold">{{ $key }}.</span><x-question-text :text="$option" :format="$question->text_format" /></div>
                                    @if ($question->option_file_paths[$key] ?? null)
                                        <x-question-attachment :path="$question->option_file_paths[$key]" :alt="'lampiran pilihan '.$key" class="mt-2" />
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 text-xs text-amber-200">Tidak memakai kunci otomatis; guru memberi nilai setelah ujian dikumpulkan.</p>
                    @endif
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-white/10 p-12 text-center text-sm text-slate-500">Belum ada soal untuk mapel dan tingkat ini.</div>
            @endforelse
        </section>
    </div>

    <div class="mt-6 rounded-2xl border border-white/10 bg-white/[0.025] p-4">
        {{ $questions->links() }}
    </div>

    <x-exam-image-dialog />
@endsection
