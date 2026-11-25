@extends('layouts.app')

@section('title', 'Edit Soal '.$question->position)
@section('eyebrow', $trial ? 'Bank Soal Uji Coba' : 'Bank Soal Inti')
@section('heading', 'Edit Soal '.$question->position)

@section('content')
    <section class="max-w-3xl rounded-3xl border border-white/10 bg-white/[0.035] p-5 sm:p-6">
        <a href="{{ $backUrl }}" class="text-sm font-semibold text-cyan-300">← Kembali ke soal {{ $question->position }}</a>
        <h2 class="mt-4 text-xl font-semibold text-white">{{ $subjectName }} · Soal {{ $question->position }}</h2>
        @if ($errors->any())
            <div role="alert" class="mt-4 rounded-xl border border-rose-400/20 bg-rose-400/10 p-4 text-sm text-rose-200">
                @foreach ($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif
        <div class="mt-5">
            <x-question-bank-form :action="$action" :question-types="$questionTypes" :question="$question" :back-url="$backUrl" :trial="$trial" />
        </div>
    </section>
    <x-exam-image-dialog />
@endsection
