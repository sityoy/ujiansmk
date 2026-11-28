@extends('layouts.app')

@section('title', 'Edit Ujian Uji Coba')
@section('eyebrow', 'Ujian Uji Coba')
@section('heading', 'Edit Ujian Uji Coba')

@section('content')
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('tryouts.index') }}" class="mb-4 inline-flex text-sm font-semibold text-cyan-300">← Daftar Ujian Uji Coba</a>

        @if ($errors->any())
            <ul class="mb-4 space-y-1 rounded-xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200" role="alert">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('tryouts.update', $trialExam) }}" class="space-y-4 rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            @csrf
            @method('PUT')
            <div>
                <p class="text-sm font-semibold text-white">{{ $trialExam->questionBank->subject->name }} · Kelas {{ $trialExam->questionBank->grade_level }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $trialExam->questionBank->name }}</p>
            </div>

            @if ($trialExam->questionBank->archived_at)
                <p class="text-sm text-amber-300">Pulihkan bank soal sebelum mengaktifkan ujian.</p>
            @endif

            @include('tryouts.settings-fields', ['trial' => $trialExam])
            <p class="text-xs leading-5 text-slate-400">Durasi yang disimpan berlaku untuk percobaan baru. Peserta yang sedang mengerjakan tetap mengikuti durasi saat mereka mulai.</p>

            <button type="submit" class="rounded-xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950">Simpan perubahan</button>
        </form>
    </div>
@endsection
