<section class="mt-4 rounded-3xl border border-white/10 bg-white/[0.035] p-4 sm:p-6">
    <h2 class="text-lg font-semibold text-white">Rekap Pembuatan Soal</h2>
    <form method="GET" action="{{ route('trial-question-banks.recap') }}" target="_blank" class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-white/10 p-4">
        @if ($archived)<input type="hidden" name="archived" value="1">@endif
        <input type="hidden" name="q" value="{{ $search }}">
        <input type="hidden" name="question_status" value="{{ $questionStatus }}">
        <label class="text-xs text-slate-400">Tingkat yang diunduh
            <select name="grade_level" class="mt-2 block rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm">
                <option value="">Semua tingkat</option>
                @foreach ($gradeLevels as $level)
                    <option value="{{ $level }}" @selected($gradeLevel === $level)>Kelas {{ $level }}</option>
                @endforeach
            </select>
        </label>
        <button class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Unduh rekap PDF / PNG</button>
        <p class="w-full text-xs leading-6 text-slate-400">Unduhan memuat seluruh hasil sesuai filter.</p>
    </form>
    <div class="mt-4 overflow-x-auto">
        <table class="data-grid w-full text-left text-sm">
            <thead><tr>
                <th scope="col" class="px-3 py-3">Guru penanggung jawab</th>
                <th scope="col" class="px-3 py-3">Mata pelajaran / bank</th>
                <th scope="col" class="px-3 py-3">Tingkat</th>
                <th scope="col" class="px-3 py-3 text-center">PG</th>
                <th scope="col" class="px-3 py-3 text-center">Isian Singkat</th>
                <th scope="col" class="px-3 py-3 text-center">Esai</th>
                <th scope="col" class="px-3 py-3 text-center">Total soal</th>
                <th scope="col" class="px-3 py-3">Status / Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-white/10">
                @forelse ($banks as $bank)
                    <tr>
                        <td class="px-3 py-4 text-slate-200">{{ $bank->teacher?->name ?? 'Belum ditetapkan' }}</td>
                        <td class="px-3 py-4">
                            <p class="font-semibold text-white">{{ $bank->subject->name }}</p>
                            <p class="text-xs text-slate-400">{{ $bank->name }}</p>
                            <p class="text-xs text-slate-400">Batas · {{ $bank->questionDeadlineLabel() ?? 'Belum ditetapkan' }}</p>
                        </td>
                        <td class="px-3 py-4">Kelas {{ $bank->grade_level }}</td>
                        <td class="px-3 py-4 text-center">{{ $bank->pg_count }}</td>
                        <td class="px-3 py-4 text-center">{{ $bank->short_answer_count }}</td>
                        <td class="px-3 py-4 text-center">{{ $bank->essay_count }}</td>
                        <td class="px-3 py-4 text-center font-semibold text-white">{{ $bank->questions_count }}</td>
                        <td class="px-3 py-4">
                            <p class="text-xs {{ $bank->questions_count > 0 ? 'text-emerald-300' : 'text-amber-300' }}">{{ $bank->questions_count > 0 ? 'Sudah ada soal' : 'Belum ada soal' }}</p>
                            <a href="{{ route('trial-question-banks.questions.index', $bank) }}" class="mt-2 inline-block whitespace-nowrap text-xs font-semibold text-cyan-300">Lihat soal →</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-10 text-center text-slate-400">Tidak ada bank soal sesuai filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
