@extends('layouts.app')
@section('title', 'Ujian Terkunci')
@section('eyebrow', 'Pemeriksaan Pengawas')
@section('heading', 'Ujian sementara terkunci')
@section('content')
    <div id="exam-security" data-state-url="{{ route('student.exams.security', $attempt) }}"
        data-event-url="{{ route('student.exams.incident', $attempt) }}" data-index-url="{{ route('student.exams.result', $attempt) }}"
        data-attempt-id="{{ $attempt->id }}" data-count="{{ $attempt->violation_count }}" data-locked="1">
        <section id="security-panel" class="rounded-3xl border border-amber-400/30 bg-slate-900 p-6">
            <p id="security-count" class="text-sm text-amber-300">Pelanggaran {{ $attempt->violation_count }}/2</p>
            <h2 id="security-title" class="mt-3 text-2xl font-semibold">Hubungi pengawas ujian</h2>
            <p id="security-message" class="mt-4 text-sm leading-7 text-slate-300">Batas dua pelanggaran tercapai. Jawaban yang sudah tersimpan tidak dihapus. Waktu tetap berjalan sampai {{ $deadline->format('H:i:s') }}; jawaban akan dikumpulkan saat waktu habis.</p>
            <button id="security-continue" type="button" class="mt-5 rounded-xl bg-cyan-400 px-5 py-3 font-semibold text-slate-950">Periksa izin pengawas</button>
            <p id="security-network" aria-live="polite" class="mt-3 text-sm text-amber-300"></p>
        </section>
    </div>
    @php($unanswered = $questions->filter(fn ($question) => blank($answers->get($question->id)?->answer)))
    @if ($unanswered->isEmpty())
        <form id="submit-locked-exam" action="{{ route('student.exams.submit', $attempt) }}" method="POST" class="mt-5">
            @csrf
            <x-exam-submit-confirmation />
            <button type="submit" class="rounded-xl border border-white/20 px-4 py-3 text-sm">Kumpulkan jawaban</button>
        </form>
        <script src="{{ asset('js/exam-submission.js') }}?v=7"></script>
        <script>
            window.bindExamSubmission({ form: document.getElementById('submit-locked-exam'), allowBlocked: true });
        </script>
    @else
        <p class="mt-5 text-sm text-slate-300">Soal nomor {{ implode(', ', \App\Services\Exams\StudentQuestionSections::unansweredNumbers($questions, $unanswered->modelKeys())) }} belum dijawab. Hubungi pengawas agar Anda bisa melanjutkan dan mengerjakan soal tersebut.</p>
    @endif
    <script src="{{ asset('js/exam-security.js') }}?v=20261119-1" defer></script>
@endsection
