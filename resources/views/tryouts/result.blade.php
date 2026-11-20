@extends('layouts.tryout')

@section('title', 'Ujian Selesai')

@section('content')
    <x-exam-completed :participant="$trialAttempt->participant_name" :exam="$trialExam->title"
        :dashboard-url="route('dashboard')">
        <p class="mt-4 text-sm leading-6 text-slate-400">Nilai dan jumlah jawaban benar tidak ditampilkan kepada peserta.</p>
    </x-exam-completed>
@endsection
