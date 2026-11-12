@extends('layouts.app')

@section('title', 'Peserta Sesi Ujian')
@section('eyebrow', 'Pemantauan Sesi')
@section('heading', $session->assessmentSubject->subject->name.' · '.$session->assessmentSubject->schoolClass->name)

@section('content')
    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-rose-400/30 bg-rose-400/10 p-4 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif
    @php
        $sessionLabels = ['draft' => 'Draf', 'published' => 'Terbit', 'active' => 'Aktif', 'closed' => 'Ditutup'];
        $checkinLabels = ['verified' => 'Terverifikasi', 'review' => 'Perlu diperiksa', 'rejected' => 'Ditolak'];
        $questionCount = $session->assessmentSubject->questions_count;
    @endphp
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('operations.index') }}" class="text-sm text-cyan-300">← Pelaksanaan ujian</a>
        <a href="{{ request()->fullUrl() }}" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Muat ulang data</a>
    </div>
    @if ($relatedSessions->count() > 1)
        <nav aria-label="Peserta per rombel pada jadwal bersama" class="mb-5 flex flex-wrap gap-2">
            @foreach ($relatedSessions->sortBy('assessmentSubject.schoolClass.name', SORT_NATURAL) as $related)
                <a href="{{ route('operations.sessions.show', $related) }}" @if ($related->id === $session->id) aria-current="page" @endif
                    class="rounded-xl border px-4 py-2 text-sm {{ $related->id === $session->id ? 'border-cyan-400/40 bg-cyan-400/10 text-cyan-300' : 'border-white/10 text-slate-400' }}">{{ $related->assessmentSubject->schoolClass->name }}</a>
            @endforeach
        </nav>
    @endif
    <section class="rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.045] p-6">
        <p class="text-xs uppercase tracking-wider text-cyan-300">{{ $session->assessmentSubject->assessmentPeriod->name }} · {{ $session->assessmentSubject->assessmentPeriod->academicYear->name }}</p>
        <h2 class="mt-3 text-xl font-semibold">{{ $session->assessmentSubject->subject->name }} · {{ $session->assessmentSubject->schoolClass->name }}</h2>
        <p class="mt-2 text-sm text-slate-300">{{ $session->starts_at->format('d/m/Y H:i') }}–{{ $session->ends_at->format('d/m/Y H:i') }} · {{ $session->duration_minutes }} menit · {{ $sessionLabels[$session->status->value] }}</p>
        <p class="mt-2 text-sm text-slate-400">{{ $session->campus->name }}{{ $session->room_name ? ' · '.$session->room_name : '' }} · {{ $session->kind->value === 'makeup' ? 'Susulan' : 'Reguler' }} · {{ $questionCount }} soal</p>
        @if (!$questionCount || !$statistics['total'] || !$session->campus->is_active)
            <p class="mt-4 text-sm text-amber-300">Perlu dilengkapi: {{ collect([!$questionCount ? 'bank soal masih kosong' : null, !$statistics['total'] ? 'belum ada peserta ditempatkan' : null, !$session->campus->is_active ? 'lokasi tidak aktif' : null])->filter()->implode('; ') }}.</p>
        @endif
        <p class="mt-4 text-xs text-slate-500">Data dimuat {{ now()->format('d/m/Y H:i:s') }} ({{ config('app.timezone') }}). Progres peserta diperbarui otomatis setiap 15 detik saat halaman aktif. Pembaruan terakhir: <span data-progress-updated>{{ now()->format('H:i:s') }}</span>. Absensi sesuai tanggal dan lokasi sesi.</p>
    </section>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        @foreach (['total' => 'Total peserta', 'scheduled' => 'Belum login / belum ujian', 'in_progress' => 'Berjalan (termasuk terkunci)', 'locked' => 'Perlu pemeriksaan', 'submitted' => 'Dikumpulkan', 'absent' => 'Tidak hadir ujian'] as $key => $label)
            <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4"><p class="text-xs text-slate-400">{{ $label }}</p><p class="mt-2 text-2xl font-semibold text-cyan-300" data-progress-stat="{{ $key }}">{{ $statistics[$key] }}</p></div>
        @endforeach
    </div>

    <section class="mt-6 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
        <div class="border-b border-white/10 p-5">
            <h2 class="text-lg font-semibold">Peserta yang ditempatkan di sesi ini</h2>
            <p class="mt-2 text-xs leading-5 text-slate-400">Progres hanya menghitung jawaban yang sudah tersimpan di server. Simpan terakhir bukan indikator online; insiden browser tetap perlu pemeriksaan pengawas. Ringkasan di atas tetap mencakup seluruh sesi meski daftar difilter.</p>
            <form method="GET" class="mt-4 flex flex-wrap gap-3">
                <input name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Cari nama, NIS, atau NISN" aria-label="Cari peserta" class="min-w-0 flex-1 rounded-xl border border-white/10 bg-slate-950 px-4 py-2.5 text-sm">
                <select name="status" aria-label="Status peserta" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                    <option value="">Semua status</option>
                    @foreach ($statusLabels as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach
                </select>
                <button class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Cari</button>
                <a href="{{ route('operations.sessions.show', $session) }}" class="px-2 py-2.5 text-sm text-slate-400">Reset</a>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-950/60 text-xs uppercase text-slate-500"><tr><th class="px-5 py-4">Peserta</th><th class="px-5 py-4">Absensi sesi</th><th class="px-5 py-4">Status ujian</th><th class="px-5 py-4">Progres</th><th class="px-5 py-4">Waktu</th><th class="px-5 py-4">Nilai</th><th class="px-5 py-4">Insiden</th></tr></thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($participants as $assignment)
                        @php
                            $attempt = $assignment->attempt;
                            $status = $attempt?->status->value ?? $assignment->status->value;
                            if ($status === 'in_progress' && $attempt->security_locked_at) $status = 'locked';
                            $checkin = $assignment->student->dailyCheckins->first();
                            $answered = $attempt?->answers_count ?? 0;
                        @endphp
                        <tr data-progress-assignment="{{ $assignment->id }}">
                            <td class="px-5 py-4"><p class="font-medium text-white">{{ $assignment->student->full_name }}</p><p class="mt-1 text-xs text-slate-500">NIS {{ $assignment->student->student_number }} · NISN {{ $assignment->student->nisn ?: '—' }}</p></td>
                            <td class="px-5 py-4 text-xs {{ $checkin?->status->value === 'verified' ? 'text-emerald-300' : 'text-amber-300' }}">{{ $checkin ? $checkinLabels[$checkin->status->value] : 'Belum tercatat' }}</td>
                            <td class="px-5 py-4 text-slate-300" data-progress-status>{{ $statusLabels[$status] ?? 'Perlu pemeriksaan' }}</td>
                            <td class="px-5 py-4 text-slate-300"><p data-progress-label data-progress-total="{{ $questionCount }}" aria-label="{{ $answered }} / {{ $questionCount }} jawaban"><span data-progress-answered>{{ $answered }}</span> / {{ $questionCount }} jawaban</p><progress data-progress-bar value="{{ $answered }}" max="{{ max(1, $questionCount) }}" aria-label="Progres jawaban {{ $assignment->student->full_name }}" class="mt-2 h-2 w-24 accent-cyan-400"></progress></td>
                            <td class="whitespace-nowrap px-5 py-4 text-xs leading-6 text-slate-400">Mulai: {{ $attempt?->started_at?->format('H:i:s') ?? '—' }}<br>Simpan terakhir: {{ $attempt?->last_seen_at?->format('H:i:s') ?? '—' }}<br>Dikumpulkan: {{ $attempt?->submitted_at?->format('H:i:s') ?? '—' }}</td>
                            <td class="px-5 py-4 text-slate-300">
                                <p>{{ $attempt?->score ?? '—' }}</p>
                                @if ($attempt && $attempt->grading_status->value !== 'automatic')
                                    <p class="mt-1 text-xs {{ $attempt->grading_status->value === 'graded' ? 'text-emerald-300' : 'text-amber-300' }}">{{ $attempt->grading_status->label() }}</p>
                                    @if (in_array(auth()->user()->role->value, ['super_admin', 'committee', 'teacher'], true))
                                        <a href="{{ route('grading.show', $attempt) }}" class="mt-2 inline-flex text-xs text-violet-300">Buka koreksi →</a>
                                    @endif
                                @endif
                            </td>
                            <td class="min-w-72 px-5 py-4 text-slate-300">
                                <p data-reset-used class="mb-2 text-xs text-amber-200">Pemulihan terpakai {{ $assignment->security_reset_count }}/2 · jatah bersama seluruh petugas</p>
                                @if ($assignment->security_reset_count >= 2)<p class="mb-2 text-xs text-rose-300">Batas reset atau izin lanjut tercapai.</p>@endif
                                <p data-progress-violations>{{ $attempt?->violation_count ?? 0 }}{{ $attempt?->security_enabled ? '/2' : '' }}</p>
                                @if ($attempt)
                                    <x-exam-incident-history :incidents="$attempt->securityIncidents" />
                                    @if ($status === 'locked' && in_array(auth()->user()->role->value, ['super_admin', 'committee', 'principal', 'proctor'], true))
                                        <form method="POST" action="{{ route('operations.attempts.security-review', $attempt) }}" class="mt-3 space-y-2" onsubmit="return confirm('Simpan keputusan pengawas? Waktu dan riwayat tidak direset.')">
                                            @csrf
                                            <input type="hidden" name="lock_version" value="{{ $attempt->security_lock_version }}">
                                            <label class="block text-xs text-amber-300">Alasan pemeriksaan (wajib)
                                                <textarea name="reason" required minlength="5" maxlength="1000" rows="2" class="mt-1 w-full rounded-lg border border-white/20 bg-slate-950 p-2 text-white"></textarea>
                                            </label>
                                            <select name="action" required aria-label="Keputusan pengawas" class="w-full rounded-lg border border-white/20 bg-slate-950 p-2 text-xs">
                                                <option value="">Pilih keputusan</option>
                                                <option value="resume" @disabled($assignment->security_reset_count >= 2)>Izinkan lanjut tanpa reset</option>
                                                <option value="submit">Akhiri dan kumpulkan jawaban</option>
                                            </select>
                                            <p class="text-xs leading-5 text-slate-400">Jika diizinkan lanjut, hitungan tetap 2/2 dan kejadian berikutnya langsung mengunci kembali.</p>
                                            <button class="rounded-lg bg-amber-400 px-3 py-2 text-xs font-semibold text-slate-950">Simpan keputusan</button>
                                        </form>
                                    @endif
                                    @if ($assignment->security_reset_count < 2 && $attempt->security_enabled && $attempt->status->value === 'in_progress' && $attempt->violation_count > 0 && in_array(auth()->user()->role->value, ['super_admin', 'committee', 'principal'], true))
                                        <form method="POST" action="{{ route('operations.attempts.reset-violations', $attempt) }}" class="mt-3 space-y-2 border-t border-white/10 pt-3" onsubmit="return confirm('Reset hitungan pelanggaran peserta ke 0? Jawaban dan riwayat tetap disimpan.')">
                                            @csrf
                                            <label class="block text-xs text-rose-200">Alasan reset pelanggaran (wajib)
                                                <textarea name="reason" required minlength="5" maxlength="1000" rows="2" class="mt-1 w-full rounded-lg border border-white/20 bg-slate-950 p-2 text-white"></textarea>
                                            </label>
                                            <p class="text-xs leading-5 text-slate-400">Reset membuka kunci dan memberi jatah 2 pelanggaran baru. Jawaban, batas waktu asli, dan riwayat kejadian tidak dihapus.</p>
                                            <button class="rounded-lg border border-rose-300/40 px-3 py-2 text-xs font-semibold text-rose-200">Reset pelanggaran ke 0</button>
                                        </form>
                                    @endif
                                    @if ($assignment->security_reset_count < 2 && in_array(auth()->user()->role->value, ['super_admin', 'committee', 'principal'], true) && $session->status->value !== 'closed' && now()->lt($session->ends_at))
                                        <details class="mt-3 border-t border-white/10 pt-3">
                                            <summary class="cursor-pointer text-xs font-semibold text-rose-300">Reset seluruh ujian peserta</summary>
                                            <form method="POST" action="{{ route('operations.assignments.reset-attempt', $assignment) }}" class="mt-3 space-y-2" onsubmit="return confirm('Reset SELURUH ujian peserta? Semua jawaban percobaan ini dihapus dan siswa harus mulai kembali dari awal.')">
                                                @csrf
                                                <textarea name="reason" required minlength="5" maxlength="1000" rows="2" placeholder="Alasan reset seluruh ujian..." class="w-full rounded-lg border border-rose-300/30 bg-slate-950 p-2 text-xs text-white"></textarea>
                                                <p class="text-xs leading-5 text-slate-400">Jawaban aktif dihapus dan peserta kembali ke status belum mulai. Ringkasan percobaan lama, petugas, dan alasan tetap tersimpan sebagai audit.</p>
                                                <button class="rounded-lg bg-rose-400 px-3 py-2 text-xs font-semibold text-slate-950">Reset ujian dari awal</button>
                                            </form>
                                        </details>
                                    @endif
                                @endif
                                @if ($assignment->attemptResets->isNotEmpty())
                                    <details class="mt-3 text-xs text-slate-400">
                                        <summary class="cursor-pointer text-rose-200">Riwayat reset ujian ({{ $assignment->attemptResets->count() }})</summary>
                                        @foreach ($assignment->attemptResets as $reset)
                                            <p class="mt-2 leading-5">{{ $reset->created_at->format('d/m/Y H:i:s') }} · {{ $reset->performedBy?->name ?? 'Akun dihapus' }}<br>{{ $reset->reason }}</p>
                                            <x-exam-incident-history :incidents="$reset->snapshot['security_incidents'] ?? []" />
                                        @endforeach
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">Tidak ada peserta sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-5"><p class="mb-3 text-xs text-slate-500">{{ $participants->total() }} peserta sesuai filter · 25 peserta per halaman.</p>{{ $participants->links() }}</div>
    </section>
    @if (in_array(auth()->user()->role->value, ['super_admin', 'committee', 'principal'], true))
        <p class="mt-5 text-sm text-slate-400">Peserta tidak hadir dapat dipindahkan melalui <a href="{{ route('scheduling.index') }}#peserta-susulan" class="text-cyan-300">Penjadwalan → Peserta Susulan</a>. Penugasan lama dipindahkan, bukan dibuat ganda; siswa yang sudah mulai tidak dapat dipindahkan.</p>
    @endif

    @if ($session->status->value !== 'closed' && $participants->isNotEmpty())
        <script>
            (() => {
                const rows = [...document.querySelectorAll('[data-progress-assignment]')];
                const assignmentIds = rows.map((row) => Number(row.dataset.progressAssignment));
                const labels = @json($statusLabels);
                const url = new URL(@json(route('operations.sessions.progress', $session)), window.location.origin);
                assignmentIds.forEach((id) => url.searchParams.append('assignment_ids[]', id));
                let loading = false;

                const refreshProgress = async () => {
                    if (loading || document.hidden) return;
                    loading = true;
                    try {
                        const response = await fetch(url, {headers: {'Accept': 'application/json'}});
                        if (!response.ok) return;
                        const data = await response.json();
                        Object.entries(data.statistics ?? {}).forEach(([key, value]) => {
                            const element = document.querySelector('[data-progress-stat="' + key + '"]');
                            if (element) element.textContent = value;
                        });
                        rows.forEach((row) => {
                            const participant = data.participants?.[row.dataset.progressAssignment];
                            if (!participant) return;
                            const status = row.querySelector('[data-progress-status]');
                            const answered = row.querySelector('[data-progress-answered]');
                            const bar = row.querySelector('[data-progress-bar]');
                            if (status) status.textContent = labels[participant.status] ?? 'Perlu pemeriksaan';
                            const violations = row.querySelector('[data-progress-violations]');
                            if (violations) violations.textContent = participant.violations + '/2';
                            const resets = row.querySelector('[data-reset-used]');
                            if (resets) resets.textContent = 'Pemulihan terpakai ' + participant.reset_used + '/2 · jatah bersama seluruh petugas';
                            if (answered) answered.textContent = participant.answered;
                            const progressLabel = row.querySelector('[data-progress-label]');
                            if (progressLabel) progressLabel.setAttribute('aria-label', participant.answered + ' / ' + progressLabel.dataset.progressTotal + ' jawaban');
                            if (bar) bar.value = participant.answered;
                        });
                        const updated = document.querySelector('[data-progress-updated]');
                        if (updated) updated.textContent = data.updated_at;
                    } catch (_) {
                        // Jaringan sementara terputus: pertahankan data terakhir dan coba lagi nanti.
                    } finally {
                        loading = false;
                    }
                };

                const timer = setInterval(refreshProgress, 15000);
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) refreshProgress();
                });
                window.addEventListener('beforeunload', () => clearInterval(timer), {once: true});
            })();
        </script>
    @endif
@endsection
