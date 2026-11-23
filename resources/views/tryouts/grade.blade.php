@extends('layouts.app')

@section('title', 'Koreksi Jawaban Uji Coba')
@section('eyebrow', 'Ujian Uji Coba')
@section('heading', 'Koreksi Jawaban')

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        <a href="{{ route('tryouts.participants', $trialExam) }}" class="text-sm font-semibold text-cyan-300">← Kembali ke peserta</a>
        <section class="rounded-2xl border border-white/10 bg-white/[0.035] p-5">
            <h2 class="text-xl font-semibold text-white">{{ $trialAttempt->participant_name }}</h2>
            <p class="mt-1 text-sm text-slate-400">{{ $trialExam->title }} · {{ $trialExam->questionBank->subject->name }}</p>
            <p class="mt-2 text-sm text-slate-400">{{ $trialAttempt->grading_status->label() }}@if ($trialAttempt->score !== null) · Nilai akhir {{ number_format((float) $trialAttempt->score, 2, ',', '.') }}@endif</p>
        </section>
        @if ($errors->any())
            <p role="alert" class="rounded-xl border border-rose-400/30 p-4 text-sm text-rose-300">{{ $errors->first() }}</p>
        @endif
        <form method="POST" action="{{ route('tryouts.attempts.grade.update', [$trialExam, $trialAttempt]) }}" class="space-y-4">
            @csrf
            @method('PUT')
            @foreach ($answers as $answer)
                @php($question = $answer->question)
                <article class="rounded-2xl border border-white/10 bg-white/[0.035] p-5">
                    <p class="mb-2 text-sm font-semibold text-cyan-300">{{ $question->question_type->label() }} · Soal {{ $loop->iteration }}</p>
                    <x-question-prompt :question="$question" />
                    <div class="mt-4 rounded-xl border border-white/10 bg-slate-950/50 p-4">
                        <p class="mb-2 text-xs font-semibold text-slate-400">Jawaban peserta</p>
                        <p class="whitespace-pre-wrap break-words text-sm text-white">{{ $answer->answer ?? 'Belum dijawab' }}</p>
                    </div>
                    <label class="mt-4 block text-sm text-slate-300">
                        Poin dari {{ (float) $question->points }}
                        <input type="number" name="scores[{{ $question->id }}]" min="0" max="{{ $question->points }}" step="0.01"
                            value="{{ old('scores.'.$question->id, $answer->points_awarded ?? ($answer->answer === null ? 0 : '')) }}" required
                            class="mt-2 block rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
                    </label>
                </article>
            @endforeach
            <button type="submit" class="rounded-xl bg-cyan-400 px-5 py-3 font-semibold text-slate-950">Simpan nilai</button>
        </form>
    </div>
    <x-exam-image-dialog />
@endsection
