@extends('layouts.app')

@section('title', 'Koreksi Jawaban Peserta')
@section('eyebrow', 'Penilaian Manual')
@section('heading', $attempt->assignment->student->full_name)

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif
    <a href="{{ route('grading.index') }}" class="text-sm text-violet-300">← Daftar koreksi</a>
    <section class="mt-5 rounded-3xl border border-violet-400/20 bg-violet-400/[0.045] p-6">
        <p class="text-xs uppercase tracking-wider text-violet-300">{{ $attempt->assignment->assessmentSubject->subject->name }} · {{ $attempt->assignment->assessmentSubject->schoolClass->name }}</p>
        <h2 class="mt-3 text-xl font-semibold text-white">{{ $attempt->assignment->student->full_name }}</h2>
        <p class="mt-2 text-sm text-slate-400">NIS {{ $attempt->assignment->student->student_number }} · Dikumpulkan {{ $attempt->submitted_at?->format('d/m/Y H:i:s') }}</p>
    </section>

    <form method="POST" action="{{ route('grading.update', $attempt) }}" class="mt-6 space-y-4">
        @csrf @method('PUT')
        @forelse ($questions as $question)
            @php($answer = $answers->get($question->id))
            <article class="rounded-2xl border border-white/10 bg-white/[0.035] p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs font-semibold text-violet-300">Soal {{ $question->position }} · {{ $question->question_type->label() }}</p>
                    <p class="text-xs text-slate-400">Bobot maksimal {{ number_format((float) $question->points, 2, ',', '.') }}</p>
                </div>
                <p class="mt-3 whitespace-pre-line text-sm leading-7 text-white">{{ $question->question_text }}</p>
                <div class="mt-4 rounded-xl border border-white/10 bg-slate-950/60 p-4">
                    <p class="text-xs text-slate-500">Jawaban siswa</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-7 {{ $answer?->answer ? 'text-slate-200' : 'text-amber-300' }}">{{ $answer?->answer ?: 'Tidak dijawab' }}</p>
                </div>
                <label class="mt-4 block text-xs text-slate-400">Nilai soal
                    <input type="number" name="scores[{{ $question->id }}]" value="{{ old('scores.'.$question->id, $answer?->points_awarded ?? 0) }}" min="0" max="{{ $question->points }}" step="0.01" required class="mt-2 w-40 rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm text-white">
                </label>
            </article>
        @empty
            <p class="rounded-2xl border border-white/10 p-6 text-sm text-slate-500">Ujian ini tidak mempunyai soal yang dikoreksi manual.</p>
        @endforelse
        @if ($questions->isNotEmpty())
            <button class="w-full rounded-xl bg-violet-400 px-5 py-4 font-semibold text-slate-950" onclick="return confirm('Simpan seluruh nilai dan hitung nilai akhir peserta?')">Simpan koreksi dan hitung nilai</button>
        @endif
    </form>
@endsection
