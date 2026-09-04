@extends('layouts.app')

@section('title', 'Bank Soal')
@section('eyebrow', 'Penjadwalan')
@section('heading', 'Bank Soal Ujian')

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-3xl border border-violet-400/20 bg-violet-400/[0.045] p-6">
        <a href="{{ route('scheduling.index') }}" class="text-xs font-medium text-violet-300">← Kembali ke penjadwalan</a>
        <p class="mt-5 text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">{{ $assessmentSubject->assessmentPeriod->name }}</p>
        <h2 class="mt-2 text-2xl font-semibold text-white">{{ $assessmentSubject->subject->name }} · {{ $assessmentSubject->schoolClass->name }}</h2>
        <p class="mt-2 text-sm text-slate-400">{{ $assessmentSubject->assessmentPeriod->academicYear->name }} · {{ $assessmentSubject->questions->count() }} soal tersedia</p>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[420px_1fr]">
        <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            <h2 class="text-xl font-semibold text-white">Tambah soal pilihan ganda</h2>
            @if ($isLocked)
                <p class="mt-3 text-sm text-amber-300">Bank soal terkunci karena sudah ada siswa yang mulai ujian. Isi dan bobot soal dipertahankan untuk menjaga konsistensi nilai reguler dan susulan.</p>
            @endif
            <fieldset @disabled($isLocked)>
            <form method="POST" action="{{ route('scheduling.questions.store', $assessmentSubject) }}" class="mt-5 space-y-3">
                @csrf
                <textarea name="question_text" rows="5" required placeholder="Tuliskan pertanyaan..." class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-violet-400">{{ old('question_text') }}</textarea>
                @foreach (['A', 'B', 'C', 'D'] as $option)
                    <div class="grid grid-cols-[36px_1fr] items-center gap-2">
                        <span class="grid size-9 place-items-center rounded-lg bg-slate-900 text-xs font-semibold text-violet-300">{{ $option }}</span>
                        <input name="option_{{ strtolower($option) }}" value="{{ old('option_'.strtolower($option)) }}" required placeholder="Pilihan {{ $option }}" class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-violet-400">
                    </div>
                @endforeach
                <div class="grid gap-3 sm:grid-cols-2">
                    <select name="correct_answer" required class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                        <option value="">Jawaban benar</option>
                        @foreach (['A', 'B', 'C', 'D'] as $option)<option value="{{ $option }}">Pilihan {{ $option }}</option>@endforeach
                    </select>
                    <input type="number" name="points" value="1" min="0.01" max="1000" step="0.01" required placeholder="Bobot" class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                </div>
                <button class="w-full rounded-xl bg-violet-400 px-4 py-3 text-sm font-semibold text-slate-950">Simpan soal</button>
            </form>
            </fieldset>
        </section>

        <section class="space-y-3">
            @forelse ($assessmentSubject->questions as $question)
                <article class="rounded-2xl border border-white/10 bg-white/[0.035] p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold text-violet-300">Soal {{ $question->position }} · {{ number_format((float) $question->points, 2, ',', '.') }} poin</p>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-white">{{ $question->question_text }}</p>
                        </div>
                        <form method="POST" action="{{ route('scheduling.questions.destroy', [$assessmentSubject, $question]) }}" onsubmit="return confirm('Hapus soal ini?')">
                            @csrf @method('DELETE')
                            <button @disabled($isLocked) class="text-xs text-rose-300 disabled:opacity-40">Hapus</button>
                        </form>
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        @foreach ($question->options as $key => $option)
                            <div class="rounded-xl border px-3 py-2 text-xs {{ $key === $question->correct_answer ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200' : 'border-white/10 text-slate-400' }}">
                                <span class="font-semibold">{{ $key }}.</span> {{ $option }}
                            </div>
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-white/10 p-12 text-center text-sm text-slate-500">Belum ada soal untuk mapel dan kelas ini.</div>
            @endforelse
        </section>
    </div>
@endsection
