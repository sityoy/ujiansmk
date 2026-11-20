@extends('layouts.app')

@section('title', 'Koreksi Jawaban Peserta')
@section('eyebrow', 'Penilaian Guru Mapel')
@section('heading', $attempt->assignment->student->full_name)

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif
    @php
        $allQuestions = $attempt->assignment->assessmentSubject->questions;
        $pgQuestions = $allQuestions->filter(fn ($question) => $question->question_type->value === 'multiple_choice');
        $pgCorrect = $pgQuestions->filter(fn ($question) => (bool) $answers->get($question->id)?->is_correct)->count();
        $pgScore = min(100, $pgCorrect * 2);
    @endphp
    <a href="{{ route('grading.index') }}" class="text-sm text-violet-300">← Daftar koreksi</a>
    <section class="mt-5 rounded-3xl border border-violet-400/20 bg-violet-400/[0.045] p-6">
        <p class="text-xs uppercase tracking-wider text-violet-300">{{ $attempt->assignment->assessmentSubject->subject->name }} · {{ $attempt->assignment->assessmentSubject->schoolClass->name }}</p>
        <h2 class="mt-3 text-xl font-semibold text-white">{{ $attempt->assignment->student->full_name }}</h2>
        <p class="mt-2 text-sm text-slate-400">NIS {{ $attempt->assignment->student->student_number }} · Dikumpulkan {{ $attempt->submitted_at?->format('d/m/Y H:i:s') }}</p>
        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4"><p class="text-xs text-slate-400">Nilai PG</p><p class="mt-2 text-2xl font-semibold text-cyan-300">{{ number_format($pgScore, 2, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">{{ $pgCorrect }} benar × 2 poin</p></div>
            <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4"><p class="text-xs text-slate-400">Nilai manual terakhir</p><p class="mt-2 text-2xl font-semibold text-amber-300">{{ $attempt->manual_score !== null ? number_format((float) $attempt->manual_score, 2, ',', '.') : '—' }}</p><p class="mt-1 text-xs text-slate-500">Essay/Isian 0–100</p></div>
            <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4"><p class="text-xs text-slate-400">Nilai akhir</p><p class="mt-2 text-2xl font-semibold text-emerald-300">{{ $attempt->score !== null ? number_format((float) $attempt->score, 2, ',', '.') : '—' }}</p><p class="mt-1 text-xs text-slate-500">Maksimal 100</p></div>
        </div>
        <p class="mt-4 rounded-xl border border-cyan-400/15 bg-cyan-400/[0.04] p-3 text-xs leading-5 text-cyan-100">Rumus jika PG dan Essay/Isian digunakan: (Nilai PG + Nilai Manual) ÷ 2. Jika hanya satu komponen, nilai komponen tersebut menjadi nilai akhir. Nilai tidak dapat melebihi 100.</p>
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
                <x-question-text :text="$question->question_text" :format="$question->text_format" class="mt-3 text-sm leading-7 text-white" />
                <div class="mt-4 rounded-xl border border-white/10 bg-slate-950/60 p-4">
                    <p class="text-xs text-slate-500">Jawaban siswa</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-7 {{ $answer?->answer ? 'text-slate-200' : 'text-amber-300' }}">{{ $answer?->answer ?: 'Tidak dijawab / dikerjakan manual di luar sistem' }}</p>
                </div>
                <label class="mt-4 block text-xs text-slate-400">Nilai soal
                    <input type="number" name="scores[{{ $question->id }}]" value="{{ old('scores.'.$question->id, $answer?->points_awarded ?? 0) }}" min="0" max="{{ $question->points }}" step="0.01" class="mt-2 w-40 rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm text-white">
                </label>
            </article>
        @empty
            <p class="rounded-2xl border border-white/10 p-6 text-sm text-slate-500">Tidak ada jawaban Essay/Isian di sistem. Guru tetap dapat memasukkan nilai manual melalui kolom berikut.</p>
        @endforelse

        <section class="rounded-2xl border border-amber-400/20 bg-amber-400/[0.045] p-5">
            <label class="block text-sm font-semibold text-amber-200">Nilai Essay/Isian manual langsung (opsional)
                <input type="number" name="manual_score" value="{{ old('manual_score', $questions->isEmpty() ? $attempt->manual_score : '') }}" min="0" max="100" step="0.01" placeholder="0–100" class="mt-3 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm text-white sm:w-56">
            </label>
            <p class="mt-3 text-xs leading-5 text-slate-400">Gunakan kolom ini apabila Essay dikerjakan secara manual/di luar sistem. Jika diisi, angka ini menjadi nilai komponen Essay/Isian dan menggantikan hasil perhitungan per soal.</p>
        </section>

        <button class="w-full rounded-xl bg-violet-400 px-5 py-4 font-semibold text-slate-950" onclick="return confirm('Simpan koreksi dan hitung ulang nilai akhir peserta?')">Simpan koreksi dan hitung nilai akhir</button>
    </form>
@endsection
