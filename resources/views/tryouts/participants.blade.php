@extends('layouts.app')

@section('title', 'Peserta Uji Coba')
@section('eyebrow', 'Pemantauan Ujian Uji Coba')
@section('heading', $trialExam->title)

@section('content')
    @if ($errors->any())<div class="mb-5 rounded-2xl border border-rose-400/20 bg-rose-400/10 p-4 text-sm text-rose-200">{{ $errors->first() }}</div>@endif

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('tryouts.index') }}" class="text-sm text-cyan-300">← Ujian uji coba</a>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('tryouts.cards', $trialExam) }}" target="_blank" class="rounded-xl bg-cyan-400/10 px-4 py-2 text-sm font-semibold text-cyan-200">Cetak kartu peserta</a>
            <a href="{{ route('tryout.entry', $trialExam) }}" target="_blank" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Buka tautan peserta</a>
        </div>
    </div>

    <section class="rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.04] p-6">
        <p class="text-xs uppercase tracking-wider text-cyan-300">{{ $trialExam->questionBank->subject->name }} · Kelas {{ $trialExam->questionBank->grade_level }}</p>
        <h2 class="mt-2 text-2xl font-semibold">{{ $trialExam->title }}</h2>
        <p class="mt-2 text-sm text-slate-400">{{ $trialExam->duration_minutes }} menit · Kode {{ $trialExam->access_code }} · Nilai peserta tidak ditampilkan</p>
    </section>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['Total peserta', $statistics['total']], ['Mengerjakan', $statistics['in_progress']], ['Terkunci', $statistics['locked']], ['Selesai', $statistics['submitted']]] as [$label, $value])
            <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4"><p class="text-xs text-slate-400">{{ $label }}</p><p class="mt-2 text-2xl font-semibold text-cyan-300">{{ $value }}</p></div>
        @endforeach
    </div>

    <section class="mt-6 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
        <div class="border-b border-white/10 p-5"><h2 class="text-lg font-semibold">Riwayat peserta</h2><p class="mt-1 text-xs text-slate-500">20 peserta per halaman. Data nilai tidak ditampilkan.</p></div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-950/60 text-xs uppercase text-slate-500"><tr><th class="px-5 py-4">Peserta</th><th class="px-5 py-4">Mulai</th><th class="px-5 py-4">Selesai</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Pelanggaran</th><th class="px-5 py-4">Tindakan</th></tr></thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($attempts as $attempt)
                        @php($locked = $attempt->status === 'in_progress' && $attempt->security_locked_at)
                        <tr>
                            <td class="px-5 py-4"><p class="font-medium text-white">{{ $attempt->participant_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $attempt->participant_identifier }}</p></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-400">{{ $attempt->started_at->format('d/m/Y H:i:s') }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-400">{{ $attempt->submitted_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
                            <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs {{ $locked ? 'bg-rose-400/10 text-rose-200' : ($attempt->status === 'submitted' ? 'bg-emerald-400/10 text-emerald-200' : 'bg-amber-400/10 text-amber-200') }}">{{ $locked ? 'Terkunci' : ($attempt->status === 'submitted' ? 'Selesai' : 'Mengerjakan') }}</span></td>
                            <td class="px-5 py-4 text-slate-300">{{ $attempt->violation_count }}/2 <span class="text-xs text-slate-500">({{ $attempt->security_incidents_count }} catatan)</span></td>
                            <td class="min-w-64 px-5 py-4">
                                @if ($attempt->status === 'in_progress' && $attempt->violation_count > 0)
                                    <details><summary class="cursor-pointer text-xs font-semibold text-rose-300">Reset pelanggaran</summary>
                                        <form method="POST" action="{{ route('tryouts.attempts.reset-violations', [$trialExam, $attempt]) }}" class="mt-3 space-y-2" onsubmit="return confirm('Reset pelanggaran peserta ini? Jawaban dan waktu tidak direset.')">
                                            @csrf
                                            <textarea name="reason" required minlength="5" maxlength="1000" rows="2" placeholder="Alasan reset..." class="w-full rounded-lg border border-white/10 bg-slate-950 p-2 text-xs"></textarea>
                                            <button class="rounded-lg border border-rose-300/30 px-3 py-2 text-xs text-rose-200">Reset ke 0/2</button>
                                        </form>
                                    </details>
                                @else
                                    <span class="text-xs text-slate-500">Tidak ada tindakan</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">Belum ada peserta yang mengikuti uji coba.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($attempts->hasPages())<div class="p-5">{{ $attempts->links() }}</div>@endif
    </section>
@endsection

