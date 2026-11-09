@extends('layouts.app')

@section('title', 'Peserta Uji Coba')
@section('eyebrow', 'Pemantauan Ujian Uji Coba')
@section('heading', $trialExam->title)

@section('content')
    @if ($errors->any())<div class="mb-5 rounded-2xl border border-rose-400/20 bg-rose-400/10 p-4 text-sm text-rose-200">{{ $errors->first() }}</div>@endif

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('operations.index') }}" class="text-sm text-cyan-300">← Pemantauan peserta</a>
        @if(in_array(auth()->user()->role->value, ['super_admin','committee'], true))
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('tryouts.cards', $trialExam) }}" target="_blank" class="rounded-xl bg-cyan-400/10 px-4 py-2 text-sm font-semibold text-cyan-200">Cetak kartu peserta</a>
                <a href="{{ route('tryout.entry', $trialExam) }}" target="_blank" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Buka tautan peserta</a>
            </div>
        @endif
    </div>

    <section class="rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.04] p-6">
        <p class="text-xs uppercase tracking-wider text-cyan-300">{{ $trialExam->questionBank->subject->name }} · Kelas {{ $trialExam->questionBank->grade_level }}</p>
        <h2 class="mt-2 text-2xl font-semibold">{{ $trialExam->title }}</h2>
        <p class="mt-2 text-sm text-slate-400">{{ $trialExam->duration_minutes }} menit · Nilai peserta tidak ditampilkan · Batas 2 pelanggaran</p>
    </section>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        @foreach (['total' => ['Total siswa', $statistics['total']], 'not_started' => ['Belum login / belum ujian', $statistics['not_started']], 'in_progress' => ['Mengerjakan', $statistics['in_progress']], 'locked' => ['Terkunci', $statistics['locked']], 'submitted' => ['Selesai', $statistics['submitted']]] as $key => [$label, $value])
            <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4"><p class="text-xs text-slate-400">{{ $label }}</p><p data-trial-stat="{{ $key }}" class="mt-2 text-2xl font-semibold text-cyan-300">{{ $value }}</p></div>
        @endforeach
    </div>

    <section class="mt-6 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
        <div class="border-b border-white/10 p-5">
            <h2 class="text-lg font-semibold">Seluruh peserta sesuai tingkat kelas</h2>
            <p class="mt-1 text-xs text-slate-500">Menampilkan siswa aktif, termasuk yang belum pernah membuka Ujian Uji Coba. Data diperbarui setiap 15 detik · pembaruan terakhir <span data-trial-updated>—</span>.</p>
            <form method="GET" class="mt-4 flex flex-wrap gap-3">
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama, NIS, atau NISN" class="min-w-0 flex-1 rounded-xl border border-white/10 bg-slate-950 px-4 py-2.5 text-sm">
                <select name="status" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                    <option value="">Semua status</option>
                    @foreach(['not_started'=>'Belum login / belum ujian','in_progress'=>'Mengerjakan','locked'=>'Terkunci','submitted'=>'Selesai'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach
                </select>
                <button class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Tampilkan</button>
                <a href="{{ route('tryouts.participants', $trialExam) }}" class="px-2 py-2.5 text-sm text-slate-400">Reset</a>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-950/60 text-xs uppercase text-slate-500"><tr><th class="px-5 py-4">Peserta</th><th class="px-5 py-4">Mulai</th><th class="px-5 py-4">Selesai</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Pelanggaran</th><th class="px-5 py-4">Tindakan</th></tr></thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($participants as $row)
                        @php
                            $student = $row['student'];
                            $attempt = $row['attempt'];
                            $locked = $attempt?->status === 'in_progress' && $attempt?->security_locked_at;
                            $status = !$attempt ? 'Belum login / belum ujian' : ($locked ? 'Terkunci' : ($attempt->status === 'submitted' ? 'Selesai' : 'Mengerjakan'));
                        @endphp
                        <tr data-trial-student="{{ $student->id }}">
                            <td class="px-5 py-4"><p class="font-medium text-white">{{ $student->full_name }}</p><p class="mt-1 text-xs text-slate-500">NIS {{ $student->student_number }} · NISN {{ $student->nisn ?: '—' }}</p></td>
                            <td data-trial-started class="whitespace-nowrap px-5 py-4 text-slate-400">{{ $attempt?->started_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
                            <td data-trial-submitted class="whitespace-nowrap px-5 py-4 text-slate-400">{{ $attempt?->submitted_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
                            <td class="px-5 py-4"><span data-trial-status class="rounded-full px-2.5 py-1 text-xs {{ !$attempt ? 'bg-slate-400/10 text-slate-300' : ($locked ? 'bg-rose-400/10 text-rose-200' : ($attempt->status === 'submitted' ? 'bg-emerald-400/10 text-emerald-200' : 'bg-amber-400/10 text-amber-200')) }}">{{ $status }}</span></td>
                            <td data-trial-violations class="px-5 py-4 text-slate-300">{{ $attempt ? $attempt->violation_count.'/2' : '—' }} @if($attempt)<span class="text-xs text-slate-500">({{ $attempt->security_incidents_count }} catatan)</span>@endif</td>
                            <td class="min-w-64 px-5 py-4">
                                @if ($attempt && $attempt->status === 'in_progress' && $attempt->violation_count > 0 && in_array(auth()->user()->role->value, ['super_admin','committee'], true))
                                    <details><summary class="cursor-pointer text-xs font-semibold text-rose-300">Reset pelanggaran</summary>
                                        <form method="POST" action="{{ route('tryouts.attempts.reset-violations', [$trialExam, $attempt]) }}" class="mt-3 space-y-2" onsubmit="return confirm('Reset pelanggaran peserta ini? Jawaban dan waktu tidak direset.')">
                                            @csrf
                                            <textarea name="reason" required minlength="5" maxlength="1000" rows="2" placeholder="Alasan reset..." class="w-full rounded-lg border border-white/10 bg-slate-950 p-2 text-xs"></textarea>
                                            <button class="rounded-lg border border-rose-300/30 px-3 py-2 text-xs text-rose-200">Reset ke 0/2</button>
                                        </form>
                                    </details>
                                @else
                                    <span class="text-xs text-slate-500">{{ $attempt ? 'Pemantauan saja' : 'Belum ada aktivitas' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">Tidak ada peserta sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-5"><p class="mb-3 text-xs text-slate-500">{{ $participants->total() }} peserta sesuai filter · 20 peserta per halaman.</p>{{ $participants->links() }}</div>
    </section>
    @if ($participants->isNotEmpty())
        <script>
            (() => {
                const rows = [...document.querySelectorAll('[data-trial-student]')];
                const url = new URL(@json(route('tryouts.progress', $trialExam)), window.location.origin);
                rows.forEach((row) => url.searchParams.append('student_ids[]', row.dataset.trialStudent));
                const labels = {not_started: 'Belum login / belum ujian', in_progress: 'Mengerjakan', locked: 'Terkunci', submitted: 'Selesai'};
                const styles = {
                    not_started: 'bg-slate-400/10 text-slate-300',
                    in_progress: 'bg-amber-400/10 text-amber-200',
                    locked: 'bg-rose-400/10 text-rose-200',
                    submitted: 'bg-emerald-400/10 text-emerald-200',
                };
                let loading = false;

                const refresh = async () => {
                    if (loading || document.hidden) return;
                    loading = true;
                    try {
                        const response = await fetch(url, {headers: {'Accept': 'application/json'}});
                        if (!response.ok) return;
                        const data = await response.json();
                        Object.entries(data.statistics ?? {}).forEach(([key, value]) => {
                            const element = document.querySelector('[data-trial-stat="' + key + '"]');
                            if (element) element.textContent = value;
                        });
                        rows.forEach((row) => {
                            const participant = data.participants?.[row.dataset.trialStudent];
                            if (!participant) return;
                            const status = row.querySelector('[data-trial-status]');
                            const started = row.querySelector('[data-trial-started]');
                            const submitted = row.querySelector('[data-trial-submitted]');
                            const violations = row.querySelector('[data-trial-violations]');
                            if (status) {
                                status.textContent = labels[participant.status] ?? 'Perlu pemeriksaan';
                                status.className = 'rounded-full px-2.5 py-1 text-xs ' + (styles[participant.status] ?? styles.not_started);
                            }
                            if (started) started.textContent = participant.started_at ?? '—';
                            if (submitted) submitted.textContent = participant.submitted_at ?? '—';
                            if (violations) violations.textContent = participant.violations === null
                                ? '—'
                                : participant.violations + '/2 (' + participant.incidents + ' catatan)';
                        });
                        const updated = document.querySelector('[data-trial-updated]');
                        if (updated) updated.textContent = data.updated_at;
                    } catch (_) {
                        // Koneksi sementara terputus; data terakhir dipertahankan.
                    } finally {
                        loading = false;
                    }
                };

                const timer = setInterval(refresh, 15000);
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) refresh();
                });
                window.addEventListener('beforeunload', () => clearInterval(timer), {once: true});
            })();
        </script>
    @endif
@endsection
