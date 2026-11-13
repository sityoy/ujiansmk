@php
    $members = $group->scheduledClasses;
    $allSessions = $members->flatMap(fn ($member) => $member->examSessions)->sortBy('starts_at');
    $schedules = $allSessions->groupBy(fn ($session) => $session->schedule_group_id ?? 'class-'.$session->id);
    $sourceSchedules = $schedules->filter(fn ($sessions) => $sessions->first()->kind->value === 'regular');
@endphp
<details class="rounded-3xl border border-white/10 bg-white/[0.035]">
    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 p-5">
        <div>
            <p class="font-semibold text-white">{{ $group->subject->name }} · Kelas {{ $group->grade_level }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $group->assessmentPeriod->name }} · {{ $group->assessmentPeriod->academicYear->name }}</p>
            <p class="mt-1 text-xs text-cyan-300">{{ $members->pluck('schoolClass.name')->join(', ') }} · {{ $schedules->count() }} jadwal</p>
        </div>
        <span class="rounded-full bg-cyan-400/10 px-3 py-1 text-xs text-cyan-200">Buka pengaturan</span>
    </summary>
    <div class="space-y-5 border-t border-white/10 p-5">
        <details class="rounded-2xl border border-cyan-400/20 bg-cyan-400/[0.04] p-4">
            <summary class="cursor-pointer font-semibold text-cyan-300">Tambah jadwal seluruh rombel</summary>
            <p class="mt-3 text-sm text-slate-400">Berlaku untuk {{ $members->pluck('schoolClass.name')->join(', ') }}. Tanggal, jam mulai, dan durasi diisi sendiri. Jam selesai dihitung dari durasi.</p>
            <form method="POST" action="{{ route('scheduling.sessions.store') }}" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="assessment_subject_id" value="{{ $group->id }}">
                <input type="hidden" name="scope" value="grade">
                @include('scheduling.partials.session-fields', ['session' => null, 'sessions' => collect()])
                <p class="text-xs text-slate-400">Sesi reguler menempatkan siswa aktif yang belum terdaftar. Untuk susulan, pindahkan peserta melalui bagian Peserta Susulan.</p>
                <button class="rounded-xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950">Buat jadwal semua rombel</button>
            </form>
        </details>

        @forelse ($schedules as $sessions)
            @php($session = $sessions->first())
            <section class="rounded-2xl border border-white/10 p-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="font-semibold text-white">{{ $session->kind->value === 'regular' ? 'Reguler' : 'Susulan' }} · {{ $session->starts_at->format('d/m/Y H:i') }}–{{ $session->ends_at->format('H:i') }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $session->campus->name }} · {{ $session->duration_minutes }} menit · {{ $sessions->pluck('assessmentSubject.schoolClass.name')->join(', ') }}</p>
                    </div>
                    <form method="POST" action="{{ route('operations.sessions.status', $session) }}" class="flex flex-wrap gap-2"
                        onsubmit="return this.elements.status.value !== 'closed' || confirm('Tutup jadwal seluruh rombel? Jawaban peserta dikumpulkan dan sesi tidak dapat dibuka ulang.')">
                        @csrf @method('PATCH')
                        <select name="status" aria-label="Status jadwal seluruh rombel" @disabled($session->status->value === 'closed') class="rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-xs">
                            @foreach ($sessionStatuses as $status)
                                <option value="{{ $status->value }}" @selected($session->status === $status)>{{ $statusLabels[$status->value] }}</option>
                            @endforeach
                        </select>
                        <button @disabled($session->status->value === 'closed') class="rounded-lg border border-cyan-400/30 px-3 py-2 text-xs text-cyan-300 disabled:opacity-40">Simpan status semua rombel</button>
                    </form>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs text-slate-400"><tr><th class="p-2">Rombel / Ruangan</th><th class="p-2">Peserta</th><th class="p-2">Belum mulai</th><th class="p-2">Mengerjakan</th><th class="p-2">Selesai</th><th class="p-2">Pemantauan</th></tr></thead>
                        <tbody>
                            @foreach ($sessions->sortBy('assessmentSubject.schoolClass.name', SORT_NATURAL) as $classSession)
                                <tr class="border-t border-white/10">
                                    <td class="p-2 text-white">{{ $classSession->assessmentSubject->schoolClass->name }}<span class="block text-xs text-slate-500">{{ $classSession->room_name ?? 'Ruangan belum diisi' }}</span></td>
                                    <td class="p-2">{{ $classSession->assignments_count }}</td>
                                    <td class="p-2 text-amber-300">{{ $classSession->pending_count }}</td>
                                    <td class="p-2 text-cyan-300">{{ $classSession->working_count }}</td>
                                    <td class="p-2 text-emerald-300">{{ $classSession->finished_count }}</td>
                                    <td class="p-2"><a href="{{ route('operations.sessions.show', $classSession) }}" class="text-xs font-semibold text-cyan-300">Lihat peserta {{ $classSession->assessmentSubject->schoolClass->name }} →</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <details class="mt-4 rounded-xl border border-white/10 p-3">
                    <summary class="cursor-pointer text-sm text-cyan-300">Edit tanggal dan waktu jadwal</summary>
                    <p class="mt-2 text-xs text-slate-400">Perubahan berlaku untuk seluruh rombel yang tercantum pada jadwal ini. Tanggal, lokasi, dan durasi dikunci setelah peserta mulai ujian.</p>
                    <form method="POST" action="{{ route('scheduling.sessions.update', $session) }}" class="mt-4 space-y-4">
                        @csrf @method('PATCH')
                        @include('scheduling.partials.session-fields')
                        <button class="rounded-lg bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Simpan perubahan jadwal</button>
                    </form>
                </details>
                <div class="mt-4 flex flex-wrap justify-between gap-3">
                    @if ($session->kind->value === 'regular' && $session->status->value !== 'closed')
                        <form method="POST" action="{{ route('scheduling.assign-class', [$session->assessmentSubject, $session]) }}">
                            @csrf
                            <button class="text-xs font-semibold text-emerald-300">Tempatkan siswa aktif semua rombel</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('scheduling.sessions.destroy', $session) }}" onsubmit="return confirm('Hapus jadwal seluruh rombel yang tercantum? Jadwal yang sudah memiliki peserta dipertahankan.')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-rose-300">Hapus jadwal</button>
                    </form>
                </div>
            </section>
        @empty
            <p class="text-sm text-slate-400">Belum ada jadwal. Tambahkan satu jadwal untuk seluruh rombel di atas.</p>
        @endforelse

        <details class="rounded-2xl border border-violet-400/20 p-4">
            <summary class="cursor-pointer text-sm font-semibold text-violet-300">Bank soal dan guru per rombel</summary>
            <div class="mt-4 space-y-4">
                @foreach ($members as $component)
                    <section class="rounded-xl border border-white/10 p-4">
                        @include('scheduling.partials.component-settings')
                    </section>
                @endforeach
            </div>
        </details>
    </div>
</details>
