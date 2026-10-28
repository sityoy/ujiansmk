@extends('layouts.app')

@section('title', 'Koreksi Jawaban')
@section('eyebrow', 'Penilaian Manual')
@section('heading', 'Koreksi Isian dan Esai')

@section('content')
    <section class="rounded-3xl border border-violet-400/20 bg-violet-400/[0.045] p-6">
        <h2 class="text-xl font-semibold text-white">Jawaban yang sudah dikumpulkan</h2>
        <p class="mt-2 text-sm leading-6 text-slate-400">Guru hanya melihat mapel/kelas yang ditugaskan kepadanya. Nilai rapor ATS baru terisi setelah seluruh isian dan esai selesai dikoreksi.</p>
    </section>

    <section class="mt-6 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-950/60 text-xs uppercase text-slate-500"><tr><th class="px-5 py-4">Peserta</th><th class="px-5 py-4">Mapel/Kelas</th><th class="px-5 py-4">Guru</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Aksi</th></tr></thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($attempts as $attempt)
                        <tr>
                            <td class="px-5 py-4"><p class="font-medium text-white">{{ $attempt->assignment->student->full_name }}</p><p class="mt-1 text-xs text-slate-500">NIS {{ $attempt->assignment->student->student_number }}</p></td>
                            <td class="px-5 py-4 text-slate-300">{{ $attempt->assignment->assessmentSubject->subject->name }} · {{ $attempt->assignment->assessmentSubject->schoolClass->name }}</td>
                            <td class="px-5 py-4 text-slate-400">{{ $attempt->assignment->assessmentSubject->teacher?->name ?? 'Belum ditentukan' }}</td>
                            <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs {{ $attempt->grading_status->value === 'graded' ? 'bg-emerald-400/10 text-emerald-200' : 'bg-amber-400/10 text-amber-200' }}">{{ $attempt->grading_status->label() }}</span><p class="mt-2 text-xs text-slate-500">Nilai: {{ $attempt->score ?? '—' }}</p></td>
                            <td class="px-5 py-4"><a href="{{ route('grading.show', $attempt) }}" class="rounded-lg border border-violet-400/30 px-3 py-2 text-xs font-semibold text-violet-200">{{ $attempt->grading_status->value === 'graded' ? 'Periksa ulang' : 'Mulai koreksi' }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">Belum ada jawaban isian atau esai yang menunggu koreksi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($attempts->hasPages())<div class="p-5">{{ $attempts->links() }}</div>@endif
    </section>
@endsection
